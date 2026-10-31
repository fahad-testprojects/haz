<?php
session_start();
if (!isset($_SESSION['admin_haz'])) {
    http_response_code(403);
    exit("Unauthorized");
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "../api/db_config.php";

function h($val) {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}

function short_name($name) {
    $s = (string)($name ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, 4, 'UTF-8');
    }
    return preg_match('/^.{0,4}/us', $s, $m) ? $m[0] : substr($s, 0, 4);
}

$res = $conn->query("SELECT * FROM matches WHERE status='running' ORDER BY updated_at DESC");

if (!$res || $res->num_rows == 0) {
    echo "<div class='card empty-state' style='text-align:center; grid-column: 1/-1;'><h3>No Active Matches right now.</h3></div>";
    exit;
}

while ($row = $res->fetch_assoc()) {
    $mid = (string)$row['match_id'];
    $g = json_decode($row['game_data'], true);
    if (!is_array($g) || empty($g['players']) || !is_array($g['players'])) {
        continue;
    }

    $mName = !empty($g['matchName']) ? $g['matchName'] : "Match " . $mid;
    $pinVal = (isset($row['pin']) && $row['pin'] !== null && $row['pin'] !== '')
        ? $row['pin']
        : ($g['pin'] ?? '—');

    $uaShort = "Unknown Device";
    $device = $g['device'] ?? null;
    if (is_array($device) && !empty($device['ua'])) {
        $ua = (string)$device['ua'];
        $p1 = strpos($ua, '(');
        $p2 = strpos($ua, ')');
        if ($p1 !== false && $p2 !== false && $p2 > $p1) {
            $uaShort = substr($ua, $p1 + 1, $p2 - $p1 - 1);
        } else {
            $uaShort = substr($ua, 0, 40);
        }
    }
    $screenInfo = (is_array($device) && !empty($device['screen'])) ? $device['screen'] : 'N/A';
    $roundSum = isset($g['roundSum']) ? (int)$g['roundSum'] : 360;

    $midAttr = h($mid);
    $midJs = h(json_encode($mid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
    ?>
    <div class="card p-card match-card" id="card-<?php echo $midAttr; ?>" data-mid="<?php echo $midAttr; ?>" style="padding: 15px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">
            <div>
                <h3 class="match-title" style="margin:0; color:var(--p); font-size: 1.1rem;"><?php echo h($mName); ?></h3>
                <div class="match-meta" style="font-size:0.7rem; color:#888;">
                    ID: <strong><?php echo $midAttr; ?></strong> | PIN: <strong><?php echo h($pinVal); ?></strong> |
                    📱 <?php echo h($uaShort); ?> (<?php echo h($screenInfo); ?>)
                </div>
            </div>
            <div style="display: flex; gap: 5px;">
                <button type="button" class="btn" style="padding:5px 8px; font-size:0.7rem; background:#10b981;" onclick="triggerAnnounce(<?php echo $midJs; ?>)" title="Announce Scores">🔊</button>
                <button type="button" class="btn" style="padding:5px 8px; font-size:0.7rem; background:#6366f1;" onclick="sendMessage(<?php echo $midJs; ?>)" title="Send Message">💬</button>
                <button type="button" class="btn btn-red" style="padding:5px 8px; font-size:0.7rem;" onclick="deleteMatch(<?php echo $midJs; ?>)">Terminate</button>
            </div>
        </div>

        <div class="match-scores" style="display:flex; gap:8px; margin-bottom:10px; overflow-x: auto;">
            <?php foreach ($g['players'] as $p) { ?>
                <div style="min-width: 70px; flex:1; background:#f0f2f5; padding:8px; border-radius:10px; text-align:center;">
                    <div style="font-size:0.65rem; color:#666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo h($p['name'] ?? ''); ?></div>
                    <div style="font-weight:bold; font-size:1rem;"><?php echo (int)($p['total'] ?? 0); ?></div>
                </div>
            <?php } ?>
        </div>

        <details id="det-<?php echo $midAttr; ?>" style="border-top: 1px solid #f8f8f8; padding-top: 5px;">
            <summary style="font-size:0.75rem; color:var(--p); cursor:pointer; font-weight: bold;">Quick Intervention &amp; Logs</summary>
            
            <div style="background:#fff9f0; padding:12px; border-radius:12px; border:1px solid #ffe8cc; margin-top: 10px;">
                <div style="display:grid; gap:6px;">
                    <?php foreach ($g['players'] as $i => $p) { ?>
                        <div style="display:flex; gap:5px; align-items:center;">
                            <span class="p-label" style="font-size:0.7rem; width:80px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600;"><?php echo h($p['name'] ?? ''); ?></span>
                            <input type="number" step="1" class="in-score" data-idx="<?php echo (int)$i; ?>" id="in-<?php echo $midAttr; ?>-<?php echo (int)$i; ?>" placeholder="0" style="flex:1; padding:5px 8px; border-radius: 6px; border: 1px solid #ddd;">
                            <button type="button" class="bolt" data-round-sum="<?php echo $roundSum; ?>" style="padding: 4px 8px;" onclick="smartFill(<?php echo $midJs; ?>, <?php echo (int)$i; ?>, this)">⚡</button>
                        </div>
                    <?php } ?>
                    <button type="button" class="btn save-round-btn" style="width:100%; margin-top:5px; padding: 8px;" onclick="saveRound(<?php echo $midJs; ?>, this)">Add Round</button>
                </div>
            </div>

            <div class="match-table-wrap">
                <table style="margin-top: 10px;">
                    <tr><th>R</th><?php foreach ($g['players'] as $p) echo "<th>" . h(short_name($p['name'] ?? '')) . "</th>"; ?></tr>
                    <?php
                    $rounds = isset($g['players'][0]['history']) && is_array($g['players'][0]['history']) ? count($g['players'][0]['history']) : 0;
                    for ($r = 0; $r < $rounds; $r++) {
                        echo "<tr><td>" . ($r + 1) . "</td>";
                        foreach ($g['players'] as $p) {
                            $val = isset($p['history'][$r]) ? (int)$p['history'][$r] : 0;
                            echo "<td>" . $val . "</td>";
                        }
                        echo "</tr>";
                    }
                    ?>
                </table>
            </div>
        </details>
    </div>
    <?php
}
?>