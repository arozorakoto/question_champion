<?php
// api.php
session_start();
header('Content-Type: application/json; charset=utf-8');

$basedir = __DIR__;
$dataDir = $basedir . '/data';
$dataFile = $dataDir . '/state.json';
$snapDir = $basedir . '/snapshots';

if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);
if (!is_dir($snapDir)) mkdir($snapDir, 0777, true);

// default state
$default = [
    'master_pass' => 'admin123', // change after first deploy in state.json or via API
    'master_user' => null,
    'players' => [], // id => {id,name,buzzed,last_buzz,last_snapshot,score}
    'playlist' => [], // array of questions {text,options:[..],correct:int,duration:int}
    'current_index' => -1,
    'current_status' => 'idle', // idle | running | finished
    'started_at' => 0,
    'last_update' => time()
];

// load or create state
$state = $default;
if (file_exists($dataFile)) {
    $json = @file_get_contents($dataFile);
    $s = json_decode($json, true);
    if (is_array($s)) $state = array_merge($state, $s);
} else {
    file_put_contents($dataFile, json_encode($state, JSON_PRETTY_PRINT));
}

function save_state($s) {
    global $dataFile;
    $s['last_update'] = time();
    file_put_contents($dataFile, json_encode($s, JSON_PRETTY_PRINT));
}

// helper to respond
function ok($data=[]) { echo json_encode(array_merge(['ok'=>true], $data)); exit; }
function err($msg='') { echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

$action = $_REQUEST['action'] ?? '';

/* -----------------------
   Master authentication
   ----------------------- */
if ($action === 'master_login') {
    $pass = $_POST['password'] ?? '';
    if ($pass === $state['master_pass']) {
        $_SESSION['is_master'] = true;
        $state['master_user'] = 'master';
        save_state($state);
        ok(['msg'=>'connected']);
    } else err('Bad password');
}

if ($action === 'master_logout') {
    unset($_SESSION['is_master']);
    ok();
}

function require_master() {
    if (empty($_SESSION['is_master'])) err('Not authorized');
}

/* -----------------------
   Register player
   ----------------------- */
if ($action === 'register') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') err('name required');
    $id = preg_replace('/[^a-z0-9_\-]/i','',strtolower($name)) . '_' . rand(1000,9999);
    global $state;
    $state['players'][$id] = [
        'id'=>$id,
        'name'=>$name,
        'buzzed'=>false,
        'last_buzz'=>0,
        'last_snapshot'=>0,
        'score'=>0
    ];
    save_state($state);
    ok(['id'=>$id,'state'=>$state]);
}

/* -----------------------
   Upload snapshot (player)
   ----------------------- */
if ($action === 'upload_snapshot') {
    $id = $_POST['id'] ?? ($_GET['id'] ?? '');
    if (!$id || !isset($state['players'][$id])) err('unknown id');
    if (!empty($_FILES['snapshot']['tmp_name'])) {
        $tmp = $_FILES['snapshot']['tmp_name'];
        $dest = $snapDir . '/' . $id . '.jpg';
        if (move_uploaded_file($tmp, $dest)) {
            $state['players'][$id]['last_snapshot'] = time();
            save_state($state);
            ok();
        } else err('move failed');
    } else {
        $b64 = $_POST['b64'] ?? '';
        if ($b64) {
            if (preg_match('/^data:image\/(png|jpeg);base64,/', $b64)) {
                $b64 = preg_replace('/^data:image\/(png|jpeg);base64,/', '', $b64);
            }
            $data = base64_decode($b64);
            if ($data === false) err('decode failed');
            $dest = $snapDir . '/' . $id . '.jpg';
            file_put_contents($dest, $data);
            $state['players'][$id]['last_snapshot'] = time();
            save_state($state);
            ok();
        } else err('no image');
    }
}

/* -----------------------
   Set playlist (master)
   payload: playlist JSON array OR textarea lines -> we accept JSON or lines
   Each question: {text: "...", options:["A","B"], correct:0, duration:10}
   ----------------------- */
if ($action === 'set_playlist') {
    require_master();
    $payload = $_POST['playlist'] ?? '';
    $list = [];
    // try parse as JSON
    $parsed = json_decode($payload, true);
    if (is_array($parsed)) {
        $list = $parsed;
    } else {
        // parse lines: format simple "Q | option1 || option2 || option3 || option4 | correctIndex | duration"
        $lines = explode("\n", $payload);
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '') continue;
            // try format: text|opt1||opt2||opt3|correct|duration
            $parts = array_map('trim', explode('|', $ln));
            if (count($parts) >= 3) {
                $q = $parts[0];
                $opts = array_map('trim', explode('||', $parts[1]));
                $correct = intval($parts[2] ?? 0);
                $duration = intval($parts[3] ?? 15);
                $list[] = ['text'=>$q,'options'=>$opts,'correct'=>$correct,'duration'=>$duration];
            } else {
                // fallback simple: whole line as text with two default options
                $list[] = ['text'=>$ln,'options'=>['Vrai','Faux'],'correct'=>0,'duration'=>15];
            }
        }
    }
    $state['playlist'] = $list;
    // reset current
    $state['current_index'] = -1;
    $state['current_status'] = 'idle';
    $state['started_at'] = 0;
    save_state($state);
    ok(['playlist_count'=>count($list)]);
}

/* -----------------------
   Master: start question (by index) or next
   ----------------------- */
if ($action === 'start_question') {
    require_master();
    $index = intval($_POST['index'] ?? -1);
    if (!isset($state['playlist'][$index])) err('bad index');
    $state['current_index'] = $index;
    $state['current_status'] = 'running';
    $state['started_at'] = time();
    // reset players buzz and answers for this question (we store last_answer per-player)
    foreach ($state['players'] as $pid => $p) {
        $state['players'][$pid]['buzzed'] = false;
        $state['players'][$pid]['last_answer'] = null;
    }
    save_state($state);
    ok(['started_at'=>$state['started_at']]);
}

/* -----------------------
   Master: stop / finish current question
   ----------------------- */
if ($action === 'finish_question') {
    require_master();
    $state['current_status'] = 'finished';
    save_state($state);
    ok();
}

/* -----------------------
   Player: get_state (player or master)
   If id provided and known -> player view (limited), else master view (full if session master)
   ----------------------- */
if ($action === 'get_state') {
    $id = $_GET['id'] ?? null;
    // recompute if time expired -> change status to finished automatically
    if ($state['current_status'] === 'running') {
        $ci = $state['current_index'];
        $dur = intval($state['playlist'][$ci]['duration'] ?? 0);
        if ($dur>0 && (time() - $state['started_at']) >= $dur) {
            $state['current_status'] = 'finished';
            save_state($state);
        }
    }
    if ($id && isset($state['players'][$id])) {
        $me = $state['players'][$id];
        $q = null;
        if ($state['current_index'] >=0 && isset($state['playlist'][$state['current_index']])) {
            $q = $state['playlist'][$state['current_index']];
        }
        // return player-limited view
        ok(['role'=>'player','me'=>$me,'question'=>$q,'current_status'=>$state['current_status'],'started_at'=>$state['started_at'],'current_index'=>$state['current_index']]);
    } else {
        // master or anonymous full view (but master session shows is_master)
        $view = $state;
        $view['is_master_session'] = !empty($_SESSION['is_master']);
        ok(['role'=>'master','state'=>$view]);
    }
}

/* -----------------------
   Buzz (player)
   ----------------------- */
if ($action === 'buzz') {
    $id = $_POST['id'] ?? '';
    if (!$id || !isset($state['players'][$id])) err('unknown id');
    // only allow buzz if question running
    if ($state['current_status'] !== 'running') err('not accepting buzz now');
    // mark buzzed (first one wins — we keep order by timestamp)
    $state['players'][$id]['buzzed'] = true;
    $state['players'][$id]['last_buzz'] = time();
    save_state($state);
    ok(['id'=>$id,'last_buzz'=>$state['players'][$id]['last_buzz']]);
}

/* -----------------------
   Release buzz (master)
   ----------------------- */
if ($action === 'release_buzz') {
    require_master();
    $id = $_POST['id'] ?? '';
    if (!$id || !isset($state['players'][$id])) err('unknown id');
    $state['players'][$id]['buzzed'] = false;
    save_state($state);
    ok();
}

/* -----------------------
   Submit answer (player) — QCM index of chosen option
   Accept only if running and within duration
   ----------------------- */
if ($action === 'submit_answer') {
    $id = $_POST['id'] ?? '';
    $choice = isset($_POST['choice']) ? intval($_POST['choice']) : null;
    if (!$id || !isset($state['players'][$id])) err('unknown id');
    if ($state['current_status'] !== 'running') err('not accepting answers');
    $ci = $state['current_index'];
    if ($ci < 0 || !isset($state['playlist'][$ci])) err('no question');
    $dur = intval($state['playlist'][$ci]['duration'] ?? 0);
    if ($dur>0 && (time() - $state['started_at']) > $dur) {
        // too late
        $state['current_status'] = 'finished';
        save_state($state);
        err('time expired');
    }
    // store answer
    $state['players'][$id]['last_answer'] = ['index'=>$ci,'choice'=>$choice,'at'=>time()];
    // check correctness and update score immediately (optional)
    $correct = intval($state['playlist'][$ci]['correct'] ?? -1);
    if ($choice !== null && $choice === $correct) {
        $state['players'][$id]['score'] = intval($state['players'][$id]['score']) + 1;
    }
    save_state($state);
    ok(['correct'=>($choice===$correct)]);
}

/* -----------------------
   Master: set current question text/options manually (not used if playlist used)
   ----------------------- */
if ($action === 'set_current_manual') {
    require_master();
    $text = $_POST['text'] ?? '';
    $opts = $_POST['options'] ?? '';
    $optsArr = is_string($opts) ? explode('||', $opts) : $opts;
    $correct = intval($_POST['correct'] ?? 0);
    $duration = intval($_POST['duration'] ?? 15);
    $q = ['text'=>$text,'options'=>$optsArr,'correct'=>$correct,'duration'=>$duration];
    // put at next index
    $state['playlist'][] = $q;
    save_state($state);
    ok(['index'=>count($state['playlist'])-1]);
}

err('unknown action');
