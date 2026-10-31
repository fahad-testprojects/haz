<?php
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

require_once "../api/db_config.php";

// Login Logic
if (isset($_POST['login'])) {
    if (($_POST['pin'] ?? '') === '2662') {
        session_regenerate_id(true);
        $_SESSION['admin_haz'] = true;
    } else {
        $error = "Invalid PIN!";
    }
}

if (!isset($_SESSION['admin_haz'])) { ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Admin Login</title><meta name="viewport" content="width=device-width, initial-scale=1">
<style>*{box-sizing:border-box;}body{background:#f0f2f5;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
.card{background:white;padding:30px;border-radius:20px;box-shadow:0 10px 25px rgba(0,0,0,0.1);text-align:center;width:320px;}
input{width:100%;padding:15px;margin:20px 0;border-radius:12px;border:1px solid #ddd;font-size:1.2rem;text-align:center;outline:none;}
button{background:#4361ee;color:white;border:none;padding:15px;width:100%;border-radius:12px;font-weight:bold;cursor:pointer;}</style>
</head><body><div class="card"><h2>FOrbit Admin</h2><form method="POST"><input type="password" name="pin" placeholder="Enter PIN" maxlength="8" autofocus><button type="submit" name="login">Login</button><?php if (isset($error)) echo "<p style='color:red;'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>"; ?></form></div></body></html>
<?php exit; }

// --- API ACTIONS ---
if (isset($_POST['action'])) {
    $action = (string)$_POST['action'];

    if ($action === 'broadcast') {
        $msg = isset($_POST['msg']) ? (string)$_POST['msg'] : '';
        $stmt = $conn->prepare("INSERT INTO meta_data (key_name, val) VALUES ('announcement', ?) ON DUPLICATE KEY UPDATE val = VALUES(val)");
        if ($stmt) {
            $stmt->bind_param("s", $msg);
            $stmt->execute();
            $stmt->close();
        }
        echo "Broadcasted!";
        exit;
    }

    if ($action === 'delete_match') {
        $mid = isset($_POST['mid']) ? (string)$_POST['mid'] : '';
        $stmt = $conn->prepare("DELETE FROM matches WHERE match_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $mid);
            $stmt->execute();
            $stmt->close();
        }
        echo "Deleted!";
        exit;
    }

    if ($action === 'send_message') {
        $mid = isset($_POST['mid']) ? (string)$_POST['mid'] : '';
        $msg = isset($_POST['msg']) ? (string)$_POST['msg'] : '';
        $stmt = $conn->prepare("UPDATE matches SET admin_message = ? WHERE match_id = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $msg, $mid);
            $stmt->execute();
            $stmt->close();
        }
        echo "Message Sent!";
        exit;
    }

    if ($action === 'trigger_announce') {
        $mid = isset($_POST['mid']) ? (string)$_POST['mid'] : '';
        $stmt = $conn->prepare("UPDATE matches SET admin_announce_flag = 1 WHERE match_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $mid);
            $stmt->execute();
            $stmt->close();
        }
        echo "Announce Triggered!";
        exit;
    }

    if ($action === 'save_round') {
        $mid = isset($_POST['mid']) ? (string)$_POST['mid'] : '';
        $scores = isset($_POST['scores']) && is_array($_POST['scores']) ? $_POST['scores'] : [];

        $stmt = $conn->prepare("SELECT game_data FROM matches WHERE match_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $mid);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                $data = json_decode($row['game_data'], true);
                if (is_array($data) && isset($data['players']) && is_array($data['players'])) {
                    $prevRounds = isset($data['players'][0]['history']) && is_array($data['players'][0]['history'])
                        ? count($data['players'][0]['history'])
                        : 0;
                    $baseRev = isset($data['rev']) ? (int)$data['rev'] : $prevRounds;

                    foreach ($data['players'] as $i => &$p) {
                        $val = isset($scores[$i]) ? intval($scores[$i]) : 0;
                        if (!isset($p['history']) || !is_array($p['history'])) {
                            $p['history'] = [];
                        }
                        $p['history'][] = $val;
                        $p['total'] = (int)($p['total'] ?? 0) + $val;
                        $p['last'] = $val;
                    }
                    unset($p);

                    $data['rev'] = $baseRev + 1;
                    $data['updatedAt'] = (int)round(microtime(true) * 1000);

                    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
                    $up = $conn->prepare("UPDATE matches SET game_data = ? WHERE match_id = ?");
                    if ($up) {
                        $up->bind_param("ss", $json, $mid);
                        $up->execute();
                        $up->close();
                    }
                    echo "Round Added!";
                    exit;
                }
            }
        }
        http_response_code(400);
        echo "Failed to add round";
        exit;
    }
}

$currentAnn = "";
$annRes = $conn->query("SELECT val FROM meta_data WHERE key_name='announcement'");
if ($annRes && ($annRow = $annRes->fetch_assoc())) {
    $currentAnn = (string)($annRow['val'] ?? '');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>FOrbit Haz: Pro Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --p: #4361ee; --bg: #f8f9fa; --card: #ffffff; --text: #1f2937; }
        * { box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: sans-serif; margin: 0; padding: 20px; }
        .nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 10px; }
        .card { background: var(--card); border-radius: 20px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; }
        input, select { padding: 10px; border: 1px solid #ddd; border-radius: 10px; }
        .btn { background: var(--p); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; font-weight: bold; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-red { background: #ef4444; }
        .bolt { background: #eef2ff; color: var(--p); border: none; border-radius: 8px; cursor: pointer; padding: 10px; }
        table { width: 100%; text-align: center; border-collapse: collapse; margin: 10px 0; font-size: 0.9rem; }
        th, td { padding: 8px; border-bottom: 1px solid #eee; }
        .stat { font-size: 2rem; font-weight: 800; color: var(--p); }
        .p-card { border-left: 5px solid var(--p); }
        .toast { position: fixed; bottom: 20px; right: 20px; background: #1f2937; color: #fff; padding: 10px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: bold; opacity: 0; pointer-events: none; transition: opacity 0.25s ease; z-index: 9999; }
        .toast.show { opacity: 1; }
    </style>
</head>
<body>
    <div class="nav">
        <div style="display:flex; align-items:center; gap:12px;">
            <h1 style="margin:0;">Dashboard</h1>
            <span id="live-status" style="font-size:0.75rem; background:#dcfce7; color:#15803d; padding:4px 10px; border-radius:20px; font-weight:bold;">● Live Sync</span>
        </div>
        <a href="?logout=1" style="color:#ef4444; text-decoration:none; font-weight:bold;">Logout</a>
    </div>

    <!-- Announcement Tool -->
    <div class="card">
        <h3 style="margin-top:0;">📢 Global Announcement</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" id="ann-msg" value="<?php echo htmlspecialchars($currentAnn, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Type a message for all players..." style="flex: 1; min-width: 200px;">
            <button type="button" class="btn" onclick="sendAnn()">Broadcast</button>
            <button type="button" class="btn btn-red" onclick="clearAnn()">Clear</button>
        </div>
    </div>

    <div id="live-matches" class="grid">
        <p>Loading active matches...</p>
    </div>

    <div id="admin-toast" class="toast"></div>

    <script>
        let isLoadingMatches = false;
        let toastTimer = null;

        function showToast(msg) {
            const t = document.getElementById('admin-toast');
            t.textContent = msg;
            t.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => t.classList.remove('show'), 2200);
        }

        function sendAnn() {
            const msg = document.getElementById('ann-msg').value;
            fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=broadcast&msg=${encodeURIComponent(msg)}`
            }).then(() => showToast(msg ? "📢 Announcement broadcasted!" : "Announcement cleared!"));
        }

        function clearAnn() {
            document.getElementById('ann-msg').value = '';
            sendAnn();
        }

        function deleteMatch(mid) {
            if (!confirm("Delete this match? Players will be disconnected.")) return;
            fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete_match&mid=${encodeURIComponent(mid)}`
            }).then(() => {
                showToast("🗑️ Match terminated");
                loadMatches();
            });
        }

        function sendMessage(mid) {
            const msg = prompt("Enter message for this board:");
            if (!msg) return;
            fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=send_message&mid=${encodeURIComponent(mid)}&msg=${encodeURIComponent(msg)}`
            }).then(() => showToast("💬 Message sent!"));
        }

        function triggerAnnounce(mid) {
            fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=trigger_announce&mid=${encodeURIComponent(mid)}`
            }).then(() => showToast("🔊 Voice announce triggered!"));
        }

        function smartFill(mid, idx, btn) {
            const card = document.getElementById(`card-${mid}`);
            if (!card) return;
            const roundSum = parseInt(btn && btn.dataset ? btn.dataset.roundSum : 360, 10) || 360;
            let current = 0;
            const inputs = card.querySelectorAll('.in-score');
            let targetInput = null;
            inputs.forEach(el => {
                if (parseInt(el.dataset.idx, 10) === idx) {
                    targetInput = el;
                } else {
                    current += (parseInt(el.value, 10) || 0);
                }
            });
            if (targetInput) {
                targetInput.value = Math.max(0, roundSum - current);
            }
        }

        function saveRound(mid, btn) {
            const card = document.getElementById(`card-${mid}`);
            if (!card) return;
            const inputs = Array.from(card.querySelectorAll('.in-score'));
            const scores = inputs.map(el => parseInt(el.value, 10) || 0);

            if (btn) btn.disabled = true;
            const params = `action=save_round&mid=${encodeURIComponent(mid)}&` +
                scores.map((s, i) => `scores[${i}]=${encodeURIComponent(s)}`).join('&');

            fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params
            })
            .then(res => {
                if (!res.ok) throw new Error('Save failed');
                inputs.forEach(el => { el.value = ''; });
                showToast("✅ Round added & synced!");
                loadMatches();
            })
            .catch(() => showToast("❌ Failed to save round"))
            .finally(() => {
                if (btn) btn.disabled = false;
            });
        }

        function loadMatches() {
            if (isLoadingMatches) return;
            isLoadingMatches = true;

            fetch('live_data.php', { credentials: 'same-origin', cache: 'no-store' })
                .then(res => {
                    if (res.status === 403) {
                        window.location.reload();
                        throw new Error('Unauthorized');
                    }
                    return res.text();
                })
                .then(html => {
                    const container = document.getElementById('live-matches');
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newCards = Array.from(doc.querySelectorAll('.match-card'));

                    if (newCards.length === 0) {
                        container.innerHTML = html;
                        return;
                    }

                    // Remove non-card placeholders ("Loading..." or "No Active Matches")
                    Array.from(container.children).forEach(child => {
                        if (!child.classList.contains('match-card')) {
                            child.remove();
                        }
                    });

                    const incomingIds = new Set(newCards.map(c => c.id));

                    // Remove cards for matches that ended or were deleted
                    Array.from(container.querySelectorAll('.match-card')).forEach(existing => {
                        if (!incomingIds.has(existing.id)) {
                            existing.remove();
                        }
                    });

                    // Patch existing cards IN-PLACE so open <details> & focused <input> are NEVER disturbed!
                    newCards.forEach((newCard, idx) => {
                        const existing = document.getElementById(newCard.id);
                        if (!existing) {
                            const refNode = container.children[idx] || null;
                            container.insertBefore(newCard, refNode);
                        } else {
                            ['.match-title', '.match-meta', '.match-scores', '.match-table-wrap'].forEach(sel => {
                                const oldEl = existing.querySelector(sel);
                                const newEl = newCard.querySelector(sel);
                                if (oldEl && newEl && oldEl.innerHTML !== newEl.innerHTML) {
                                    oldEl.innerHTML = newEl.innerHTML;
                                }
                            });

                            const oldLabels = existing.querySelectorAll('.p-label');
                            const newLabels = newCard.querySelectorAll('.p-label');
                            if (oldLabels.length === newLabels.length) {
                                newLabels.forEach((nl, i) => {
                                    if (oldLabels[i].textContent !== nl.textContent) {
                                        oldLabels[i].textContent = nl.textContent;
                                    }
                                });
                            }

                            const oldBolts = existing.querySelectorAll('.bolt[data-round-sum]');
                            const newBolts = newCard.querySelectorAll('.bolt[data-round-sum]');
                            if (oldBolts.length === newBolts.length) {
                                newBolts.forEach((nb, i) => {
                                    oldBolts[i].dataset.roundSum = nb.dataset.roundSum;
                                });
                            }
                        }
                    });
                })
                .catch(() => {})
                .finally(() => {
                    isLoadingMatches = false;
                });
        }

        // Auto Refresh every 5s without resetting inputs or losing focus
        setInterval(loadMatches, 5000);
        loadMatches();
    </script>
</body>
</html>