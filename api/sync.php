<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
require_once "db_config.php";

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || !isset($input['match_id'])) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

$match_id = $conn->real_escape_string($input['match_id']);
$action = $input['action'];

// --- AUTO DB PATCH (Updated for v8.0) ---
$conn->query("ALTER TABLE matches ADD COLUMN IF NOT EXISTS pin VARCHAR(10) DEFAULT NULL AFTER match_id");
$conn->query("ALTER TABLE matches ADD INDEX IF NOT EXISTS idx_match_id (match_id)");

// 1. Fetch Global Announcement
$ann_res = $conn->query("SELECT val FROM meta_data WHERE key_name='announcement'");
$announcement = "";
if ($ann_res) {
    $ann_row = $ann_res->fetch_assoc();
    $announcement = $ann_row ? $ann_row['val'] : "";
}

// 2. Check if Match Exists
$res = $conn->query("SELECT game_data, admin_message, admin_announce_flag, pin FROM matches WHERE match_id='$match_id'");
$row = null;
if ($res) {
    $row = $res->fetch_assoc();
}

if (!$row && $action !== 'push') {
    echo json_encode([
        "status" => "success", 
        "is_deleted" => ($action === 'get_announcement' || $match_id === 'GLOBAL') ? false : true, 
        "announcement" => $announcement
    ]);
    exit;
}

$game_data_raw = isset($input['game_data']) ? json_encode($input['game_data']) : null;
$game_data_esc = $game_data_raw ? $conn->real_escape_string($game_data_raw) : null;
$status = isset($input['game_data']['status']) ? $conn->real_escape_string($input['game_data']['status']) : 'running';
$client_pin = isset($input['pin']) ? $conn->real_escape_string($input['pin']) : null;

$resp_msg = "";
$resp_announce = 0;
$final_data = null;
$access = "view"; // Default access

if ($row) {
    $resp_msg = $row['admin_message'] ?? "";
    $resp_announce = $row['admin_announce_flag'] ?? 0;
    $final_data = json_decode($row['game_data'], true);
    $db_pin = $row['pin'];
    
    // Check PIN for Editor access
    if ($client_pin !== null && $client_pin === $db_pin) {
        $access = "edit";
    }
}

// 3. Logic for Push/Sync
if ($action == 'push') {
    $new_pin = isset($input['game_data']['pin']) ? $conn->real_escape_string($input['game_data']['pin']) : null;
    if (!$row) {
        // Create new match with PIN
        $conn->query("INSERT INTO matches (match_id, game_data, status, pin) VALUES ('$match_id', '$game_data_esc', '$status', '$new_pin')");
        $final_data = $input['game_data'];
        $access = "edit";
    } else if ($access === "edit") {
        // Update existing match only if PIN matches
        $conn->query("UPDATE matches SET game_data='$game_data_esc', status='$status' WHERE match_id='$match_id'");
        $final_data = $input['game_data'];
    }
} else if ($action == 'sync') {
    if ($row) {
        $server_game = $final_data;
        $app_game = isset($input['game_data']) ? $input['game_data'] : null;
        
        if ($access === "edit" && $app_game) {
            $srv_rounds = count($server_game['players'][0]['history']);
            $app_rounds = count($app_game['players'][0]['history']);
            
            if ($app_rounds > $srv_rounds) {
                $conn->query("UPDATE matches SET game_data='$game_data_esc', status='$status' WHERE match_id='$match_id'");
                $final_data = $app_game;
            } else {
                $final_data = $server_game;
            }
        } else {
            // View-only mode: Always return server data
            $final_data = $server_game;
        }
    }
}

// Clear message and flag AFTER processing the sync (only for editors)
if ($row && $access === "edit" && ($resp_msg !== "" || $resp_announce == 1)) {
    $conn->query("UPDATE matches SET admin_message='', admin_announce_flag=0 WHERE match_id='$match_id'");
}

echo json_encode([
    "status" => "success", 
    "game_data" => $final_data, 
    "announcement" => $announcement,
    "admin_message" => $resp_msg,
    "admin_announce_flag" => (int)$resp_announce,
    "access" => $access,
    "is_deleted" => false
]);
?>