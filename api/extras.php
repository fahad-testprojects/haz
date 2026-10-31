<?php
// Optional features. Reactions are stored separately from match scores.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once 'db_config.php';
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { http_response_code(400); echo json_encode(['error'=>'Invalid request']); exit; }
$action = $input['action'] ?? '';
if ($action === 'leaderboard') {
    $result = $conn->query("SELECT status, game_data FROM matches");
    if (!$result) { http_response_code(500); echo json_encode(['error'=>'Database error']); exit; }
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $g = json_decode($row['game_data'], true);
        if (!is_array($g) || empty($g['players']) || !is_array($g['players'])) continue;
        $players = $g['players'];
        $max = max(array_map(function($p) { return (int)($p['total'] ?? 0); }, $players));
        // Legacy versions sometimes left a completed match marked running.
        if (($row['status'] ?? '') !== 'finished' && ($max <= 0 || $max < (int)($g['target'] ?? 1000))) continue;
        foreach ($players as $p) {
            $name = trim((string)($p['name'] ?? '')); if ($name === '') continue;
            if (!isset($stats[$name])) $stats[$name] = ['name'=>$name,'matches'=>0,'wins'=>0,'points'=>0,'rounds'=>0,'ducks'=>0,'sweeps'=>0];
            $stats[$name]['matches']++; $stats[$name]['points'] += (int)($p['total'] ?? 0);
            if ((int)($p['total'] ?? 0) === $max) $stats[$name]['wins']++;
            foreach ((isset($p['history']) && is_array($p['history']) ? $p['history'] : []) as $score) { $stats[$name]['rounds']++; if ((int)$score === 0) $stats[$name]['ducks']++; if ((int)$score === (int)($g['roundSum'] ?? 360)) $stats[$name]['sweeps']++; }
        }
    }
    $stats = array_values($stats);
    usort($stats, function($a,$b) { return ($b['wins'] <=> $a['wins']) ?: ($b['points'] <=> $a['points']); });
    echo json_encode(['players'=>$stats], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
$mid = trim((string)($input['match_id'] ?? ''));
if ($mid === '' || strlen($mid) > 100) { http_response_code(400); echo json_encode(['error'=>'Invalid match']); exit; }
$stmt = $conn->prepare('SELECT 1 FROM matches WHERE match_id = ? LIMIT 1');
if (!$stmt) { http_response_code(500); echo json_encode(['error'=>'Database error']); exit; }
$stmt->bind_param('s', $mid); $stmt->execute(); $exists = $stmt->get_result()->fetch_row(); $stmt->close();
if (!$exists) { http_response_code(404); echo json_encode(['error'=>'Match not found']); exit; }
if ($action === 'reaction_send') {
    $kind = (string)($input['kind'] ?? '');
    if (!in_array($kind, ['fire','duck','laugh','clap'], true)) { http_response_code(400); echo json_encode(['error'=>'Invalid reaction']); exit; }
    $stmt = $conn->prepare('INSERT INTO match_reactions (match_id, kind) VALUES (?, ?)');
    if (!$stmt) { http_response_code(503); echo json_encode(['error'=>'Reactions need database migration']); exit; }
    $stmt->bind_param('ss', $mid, $kind);
    if (!$stmt->execute()) { http_response_code(500); echo json_encode(['error'=>'Could not send reaction']); exit; }
    $id = $stmt->insert_id; $stmt->close(); echo json_encode(['id'=>$id]); exit;
}
if ($action === 'reaction_poll') {
    $after = max(0, (int)($input['after'] ?? 0));
    // A new participant starts at the current cursor; old reactions are not replayed.
    if (!isset($input['after']) || $input['after'] === null) {
        $stmt = $conn->prepare('SELECT COALESCE(MAX(id),0) FROM match_reactions WHERE match_id = ?');
        if (!$stmt) { http_response_code(503); echo json_encode(['error'=>'Reactions need database migration']); exit; }
        $stmt->bind_param('s', $mid); $stmt->execute(); $row = $stmt->get_result()->fetch_row(); $stmt->close();
        echo json_encode(['cursor'=>(int)$row[0], 'events'=>[]]); exit;
    }
    $stmt = $conn->prepare('SELECT id, kind FROM match_reactions WHERE match_id = ? AND id > ? AND created_at >= NOW() - INTERVAL 30 SECOND ORDER BY id ASC LIMIT 20');
    if (!$stmt) { http_response_code(503); echo json_encode(['error'=>'Reactions need database migration']); exit; }
    $stmt->bind_param('si', $mid, $after); $stmt->execute(); $result = $stmt->get_result(); $events = [];
    while ($r = $result->fetch_assoc()) $events[] = ['id'=>(int)$r['id'], 'kind'=>$r['kind']];
    $stmt->close(); echo json_encode(['cursor'=>count($events) ? end($events)['id'] : $after, 'events'=>$events]); exit;
}
http_response_code(400); echo json_encode(['error'=>'Unknown action']);
