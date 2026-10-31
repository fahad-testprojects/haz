<?php
session_start();
require_once "../api/db_config.php";

// Login Logic
if(isset($_POST['login'])) {
    if($_POST['pin'] === '2662') { $_SESSION['admin_haz'] = true; } 
    else { $error = "Invalid PIN!"; }
}

if(!isset($_SESSION['admin_haz'])) { ?>
<!DOCTYPE html><html><head><title>Admin Login</title><meta name="viewport" content="width=device-width, initial-scale=1">
<style>body{background:#f0f2f5;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
.card{background:white;padding:30px;border-radius:20px;box-shadow:0 10px 25px rgba(0,0,0,0.1);text-align:center;width:300px;}
input{width:100%;padding:15px;margin:20px 0;border-radius:12px;border:1px solid #ddd;font-size:1.2rem;text-align:center;outline:none;}
button{background:#4361ee;color:white;border:none;padding:15px;width:100%;border-radius:12px;font-weight:bold;cursor:pointer;}</style>
</head><body><div class="card"><h2>FOrbit Admin</h2><form method="POST"><input type="password" name="pin" placeholder="Enter PIN" maxlength="4" autofocus><button type="submit" name="login">Login</button><?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?></form></div></body></html>
<?php exit; }

// --- API ACTIONS ---
if(isset($_POST['action'])) {
    $action = $_POST['action'];
    if($action === 'broadcast') {
        $msg = $conn->real_escape_string($_POST['msg']);
        $conn->query("UPDATE meta_data SET val='$msg' WHERE key_name='announcement'");
        echo "Broadcasted!"; exit;
    }
    if($action === 'delete_match') {
        $mid = $conn->real_escape_string($_POST['mid']);
        $conn->query("DELETE FROM matches WHERE match_id='$mid'");
        echo "Deleted!"; exit;
    }
    if($action === 'send_message') {
        $mid = $conn->real_escape_string($_POST['mid']);
        $msg = $conn->real_escape_string($_POST['msg']);
        $conn->query("UPDATE matches SET admin_message='$msg' WHERE match_id='$mid'");
        echo "Message Sent!"; exit;
    }
    if($action === 'trigger_announce') {
        $mid = $conn->real_escape_string($_POST['mid']);
        $conn->query("UPDATE matches SET admin_announce_flag=1 WHERE match_id='$mid'");
        echo "Announce Triggered!"; exit;
    }
    if($action === 'save_round') {
        $mid = $conn->real_escape_string($_POST['mid']);
        $scores = $_POST['scores'];
        $res = $conn->query("SELECT game_data FROM matches WHERE match_id='$mid'");
        $row = $res->fetch_assoc();
        if($row) {
            $data = json_decode($row['game_data'], true);
            foreach($data['players'] as $i => &$p) {
                $val = intval($scores[$i]);
                $p['history'][] = $val;
                $p['total'] += $val;
                $p['last'] = $val;
            }
            $json = $conn->real_escape_string(json_encode($data));
            $conn->query("UPDATE matches SET game_data='$json' WHERE match_id='$mid'");
            echo "Round Added!"; exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>FOrbit Haz: Pro Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --p: #4361ee; --bg: #f8f9fa; --card: #ffffff; --text: #1f2937; }
        body { background: var(--bg); color: var(--text); font-family: sans-serif; margin: 0; padding: 20px; }
        .nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card { background: var(--card); border-radius: 20px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; }
        input, select { padding: 10px; border: 1px solid #ddd; border-radius: 10px; }
        .btn { background: var(--p); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; font-weight: bold; }
        .btn-red { background: #ef4444; }
        .bolt { background: #eef2ff; color: var(--p); border: none; border-radius: 8px; cursor: pointer; padding: 10px; }
        table { width: 100%; text-align: center; border-collapse: collapse; margin: 10px 0; font-size: 0.9rem; }
        th, td { padding: 8px; border-bottom: 1px solid #eee; }
        .stat { font-size: 2rem; font-weight: 800; color: var(--p); }
        .p-card { border-left: 5px solid var(--p); }
    </style>
</head>
<body>
    <div class="nav">
        <h1>Dashboard</h1>
        <a href="?logout" style="color:#666; text-decoration:none; font-weight:bold;">Logout</a>
    </div>

    <!-- Announcement Tool -->
    <div class="card">
        <h3>📢 Global Announcement</h3>
        <div style="display: flex; gap: 10px;">
            <input type="text" id="ann-msg" placeholder="Type a message for all players..." style="flex: 1;">
            <button class="btn" onclick="sendAnn()">Broadcast</button>
            <button class="btn btn-red" onclick="clearAnn()">Clear</button>
        </div>
    </div>

    <div id="live-matches" class="grid">
        <!-- Live matches will load here via AJAX -->
        <p>Loading active matches...</p>
    </div>

    <script>
        function sendAnn() {
            let msg = document.getElementById('ann-msg').value;
            fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=broadcast&msg=${encodeURIComponent(msg)}`
            }).then(() => alert("Sent!"));
        }

        function clearAnn() {
            document.getElementById('ann-msg').value = '';
            sendAnn();
        }

        function deleteMatch(mid) {
            if(!confirm("Delete this match? Player will be disconnected.")) return;
            fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete_match&mid=${mid}`
            }).then(() => loadMatches());
        }

        function sendMessage(mid) {
            let msg = prompt("Enter message for this board:");
            if(!msg) return;
            fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=send_message&mid=${mid}&msg=${encodeURIComponent(msg)}`
            }).then(() => alert("Message Sent!"));
        }

        function triggerAnnounce(mid) {
            fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=trigger_announce&mid=${mid}`
            }).then(() => alert("Announcement Triggered!"));
        }

        function smartFill(mid, id, roundSum) {
            let current = 0;
            let inputs = document.querySelectorAll(`.in-${mid}`);
            inputs.forEach(el => {
                if(el.id !== `in-${mid}-${id}`) current += (parseInt(el.value) || 0);
            });
            document.getElementById(`in-${mid}-${id}`).value = Math.max(0, roundSum - current);
        }

        function saveRound(mid) {
            let inputs = document.querySelectorAll(`.in-${mid}`);
            let scores = Array.from(inputs).map(el => el.value || 0);
            fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=save_round&mid=${mid}&` + scores.map((s,i) => `scores[${i}]=${s}`).join('&')
            }).then(() => { alert("Round Added!"); loadMatches(); });
        }

        function loadMatches() {
            // Save open states
            let openMatches = [];
            document.querySelectorAll('details[open]').forEach(el => {
                openMatches.push(el.id);
            });

            fetch('live_data.php').then(res => res.text()).then(html => {
                document.getElementById('live-matches').innerHTML = html;
                
                // Restore open states
                openMatches.forEach(id => {
                    let el = document.getElementById(id);
                    if(el) el.setAttribute('open', 'true');
                });
            });
        }

        // Auto Refresh
        setInterval(loadMatches, 5000);
        loadMatches();
    </script>
</body>
</html><?php if(isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); } ?>