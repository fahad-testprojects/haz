<?php
require_once "../api/db_config.php";

$res = $conn->query("SELECT * FROM matches WHERE status='running' ORDER BY updated_at DESC");

if ($res->num_rows == 0) {
    echo "<div class='card' style='text-align:center; grid-column: 1/-1;'><h3>No Active Matches right now.</h3></div>";
    exit;
}

while ($row = $res->fetch_assoc()) {
    $mid = $row['match_id'];
    $g = json_decode($row['game_data'], true);
    $mName = $g['matchName'] ?? "Unnamed Match";
    $device = $g['device'] ?? null;
    $uaShort = $device ? substr($device['ua'], strpos($device['ua'], '(')+1, strpos($device['ua'], ')') - strpos($device['ua'], '(')-1) : "Unknown Device";
    ?>
    <div class="card p-card" style="padding: 15px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">
            <div>
                <h3 style="margin:0; color:var(--p); font-size: 1.1rem;"><?php echo $mName; ?></h3>
                <div style="font-size:0.7rem; color:#888;">
                    ID: <?php echo $mid; ?> | 
                    📱 <?php echo $uaShort; ?> (<?php echo $device['screen'] ?? 'N/A'; ?>)
                </div>
            </div>
            <div style="display: flex; gap: 5px;">
                <button class="btn" style="padding:5px 8px; font-size:0.7rem; background:#10b981;" onclick="triggerAnnounce('<?php echo $mid; ?>')" title="Announce Scores">🔊</button>
                <button class="btn" style="padding:5px 8px; font-size:0.7rem; background:#6366f1;" onclick="sendMessage('<?php echo $mid; ?>')" title="Send Message">💬</button>
                <button class="btn btn-red" style="padding:5px 8px; font-size:0.7rem;" onclick="deleteMatch('<?php echo $mid; ?>')">Terminate</button>
            </div>
        </div>

        <div style="display:flex; gap:8px; margin-bottom:10px; overflow-x: auto;">
            <?php foreach($g['players'] as $p) { ?>
                <div style="min-width: 70px; flex:1; background:#f0f2f5; padding:8px; border-radius:10px; text-align:center;">
                    <div style="font-size:0.65rem; color:#666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $p['name']; ?></div>
                    <div style="font-weight:bold; font-size:1rem;"><?php echo $p['total']; ?></div>
                </div>
            <?php } ?>
        </div>

        <details id="det-<?php echo $mid; ?>" style="border-top: 1px solid #f8f8f8; padding-top: 5px;">
            <summary style="font-size:0.75rem; color:var(--p); cursor:pointer; font-weight: bold;">Quick Intervention & Logs</summary>
            
            <div style="background:#fff9f0; padding:12px; border-radius:12px; border:1px solid #ffe8cc; margin-top: 10px;">
                <div style="display:grid; gap:5px;">
                    <?php foreach($g['players'] as $i => $p) { ?>
                        <div style="display:flex; gap:5px; align-items:center;">
                            <span style="font-size:0.7rem; width:80px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600;"><?php echo $p['name']; ?></span>
                            <input type="number" class="in-<?php echo $mid; ?>" id="in-<?php echo $mid; ?>-<?php echo $i; ?>" placeholder="0" style="flex:1; padding:3px; border-radius: 5px; border: 1px solid #ddd;">
                            <button class="bolt" style="padding: 2px 6px;" onclick="smartFill('<?php echo $mid; ?>', <?php echo $i; ?>, <?php echo $g['roundSum']; ?>)">⚡</button>
                        </div>
                    <?php } ?>
                    <button class="btn" style="width:100%; margin-top:5px; padding: 6px;" onclick="saveRound('<?php echo $mid; ?>')">Add Round</button>
                </div>
            </div>

            <table style="margin-top: 10px;">
                <tr><th>R</th><?php foreach($g['players'] as $p) echo "<th>".substr($p['name'],0,3)."</th>"; ?></tr>
                <?php 
                $rounds = count($g['players'][0]['history']);
                for($r=0; $r<$rounds; $r++) {
                    echo "<tr><td>".($r+1)."</td>";
                    foreach($g['players'] as $p) echo "<td>".$p['history'][$r]."</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </details>
    </div>
    <?php
}
?>