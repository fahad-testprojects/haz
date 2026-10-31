<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once "db_config.php";

$raw_body = file_get_contents("php://input");
$input = json_decode($raw_body, true);
if (!is_array($input) || !isset($input['match_id']) || trim((string)$input['match_id']) === '') {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

function get_game_rev($g) {
    if (!is_array($g)) return 0;
    if (isset($g['rev'])) return (int)$g['rev'];
    return (isset($g['players'][0]['history']) && is_array($g['players'][0]['history']))
        ? count($g['players'][0]['history'])
        : 0;
}

function ensure_pin_schema($conn) {
    $conn->query("ALTER TABLE matches ADD COLUMN IF NOT EXISTS pin VARCHAR(10) DEFAULT NULL AFTER match_id");
    $conn->query("ALTER TABLE matches ADD INDEX IF NOT EXISTS idx_match_id (match_id)");
}

$match_id = trim((string)$input['match_id']);
$action = isset($input['action']) ? (string)$input['action'] : 'sync';

// 1. Fetch Global Announcement
$announcement = "";
$ann_res = $conn->query("SELECT val FROM meta_data WHERE key_name='announcement'");
if ($ann_res && ($ann_row = $ann_res->fetch_assoc())) {
    $announcement = (string)($ann_row['val'] ?? "");
}

if ($action === 'get_announcement' || $match_id === 'GLOBAL') {
    echo json_encode([
        "status" => "success",
        "is_deleted" => false,
        "announcement" => $announcement
    ]);
    exit;
}

// 2. Check if Match Exists
$stmt = $conn->prepare("SELECT * FROM matches WHERE match_id = ? LIMIT 1");
$row = null;
if ($stmt) {
    $stmt->bind_param("s", $match_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

// Auto-patch schema only if `pin` column is missing
if ($row && !array_key_exists('pin', $row)) {
    ensure_pin_schema($conn);
    $row['pin'] = null;
}

if (!$row && $action !== 'push') {
    echo json_encode([
        "status" => "success",
        "game_data" => null,
        "is_deleted" => true,
        "announcement" => $announcement
    ]);
    exit;
}

$app_game = (isset($input['game_data']) && is_array($input['game_data'])) ? $input['game_data'] : null;
$client_pin = (isset($input['pin']) && $input['pin'] !== null) ? trim((string)$input['pin']) : '';
if ($client_pin === '' && is_array($app_game) && isset($app_game['pin']) && $app_game['pin'] !== null) {
    $client_pin = trim((string)$app_game['pin']);
}

$resp_msg = "";
$resp_announce = 0;
$server_game = null;
$final_data = null;
$access = "view";
$conflict = false;
$denied = false;
$db_pin = "";

if ($row) {
    $resp_msg = (string)($row['admin_message'] ?? "");
    $resp_announce = (int)($row['admin_announce_flag'] ?? 0);
    $server_game = json_decode($row['game_data'], true);
    if (is_array($server_game)) {
        $server_game['rev'] = get_game_rev($server_game);
    }
    $final_data = $server_game;

    if (isset($row['pin']) && $row['pin'] !== null && trim((string)$row['pin']) !== '') {
        $db_pin = trim((string)$row['pin']);
    } elseif (is_array($server_game) && isset($server_game['pin']) && trim((string)$server_game['pin']) !== '') {
        $db_pin = trim((string)$server_game['pin']);
    }

    if ($client_pin !== '' && $db_pin !== '' && hash_equals($db_pin, $client_pin)) {
        $access = "edit";
    }
}

// 3. Logic for Push vs Sync
if ($action === 'push' && is_array($app_game)) {
    $status = isset($app_game['status']) ? (string)$app_game['status'] : 'running';

    if (!$row) {
        // Create new match with PIN
        $new_pin = $client_pin;
        $app_game['rev'] = max(1, get_game_rev($app_game));
        $app_game['updatedAt'] = (int)round(microtime(true) * 1000);
        if ($new_pin !== '') {
            $app_game['pin'] = $new_pin;
        }
        $json = json_encode($app_game, JSON_UNESCAPED_UNICODE);

        $ins = $conn->prepare("INSERT INTO matches (match_id, game_data, status, pin) VALUES (?, ?, ?, ?)");
        if (!$ins) {
            ensure_pin_schema($conn);
            $ins = $conn->prepare("INSERT INTO matches (match_id, game_data, status, pin) VALUES (?, ?, ?, ?)");
        }
        if ($ins) {
            $pin_param = $new_pin !== '' ? $new_pin : null;
            $ins->bind_param("ssss", $match_id, $json, $status, $pin_param);
            $ins->execute();
            $ins->close();
        }
        $db_pin = $new_pin;
        $final_data = $app_game;
        $access = "edit";
    } elseif ($access === "edit") {
        $srv_rev = get_game_rev($server_game);
        $base_rev = (isset($input['base_rev']) && $input['base_rev'] !== null) ? (int)$input['base_rev'] : null;

        if ($base_rev !== null && $base_rev < $srv_rev) {
            // Another device or Admin already saved a newer revision; return authoritative server state
            $conflict = true;
            $final_data = $server_game;
        } else {
            $app_game['rev'] = max($srv_rev + 1, get_game_rev($app_game));
            $app_game['updatedAt'] = (int)round(microtime(true) * 1000);
            if ($db_pin !== '') {
                $app_game['pin'] = $db_pin;
            }
            $json = json_encode($app_game, JSON_UNESCAPED_UNICODE);

            $up = $conn->prepare("UPDATE matches SET game_data = ?, status = ?, pin = COALESCE(NULLIF(pin, ''), ?) WHERE match_id = ?");
            if ($up) {
                $pin_param = $db_pin !== '' ? $db_pin : null;
                $up->bind_param("ssss", $json, $status, $pin_param, $match_id);
                $up->execute();
                $up->close();
            }
            $final_data = $app_game;
        }
    } else {
        // Attempted push without valid editor PIN
        $denied = true;
        $final_data = $server_game;
    }
} else {
    // action === 'sync': always return authoritative server data (never overwrite DB on a poll)
    $final_data = $server_game;
}

// Clear one-shot admin message & announce flag once delivered to an editor
if ($row && $access === "edit" && ($resp_msg !== "" || $resp_announce === 1)) {
    $clr = $conn->prepare("UPDATE matches SET admin_message = '', admin_announce_flag = 0 WHERE match_id = ?");
    if ($clr) {
        $clr->bind_param("s", $match_id);
        $clr->execute();
        $clr->close();
    }
}

// Expose PIN only to authenticated editors, strip for viewers
if (is_array($final_data)) {
    if ($access === "edit" && $db_pin !== '') {
        $final_data['pin'] = $db_pin;
    } else {
        unset($final_data['pin']);
    }
}

echo json_encode([
    "status" => "success",
    "game_data" => $final_data,
    "announcement" => $announcement,
    "admin_message" => $resp_msg,
    "admin_announce_flag" => $resp_announce,
    "access" => $access,
    "conflict" => $conflict,
    "denied" => $denied,
    "is_deleted" => false
], JSON_UNESCAPED_UNICODE);
?>