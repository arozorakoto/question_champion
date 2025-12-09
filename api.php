<?php
// api.php
header('Content-Type: application/json; charset=utf-8');

$basedir = __DIR__;
$dataFile = $basedir . '/data/state.json';
$snapDir = $basedir . '/snapshots';

// ensure directories
if (!is_dir($basedir . '/data')) mkdir($basedir . '/data', 0777, true);
if (!is_dir($snapDir)) mkdir($snapDir, 0777, true);

// load state
$state = ['players'=>[], 'current_question'=>'', 'playlist'=>[], 'master'=>null, 'last_update'=>time()];
if (file_exists($dataFile)) {
    $json = file_get_contents($dataFile);
    $s = json_decode($json, true);
    if (is_array($s)) $state = array_merge($state, $s);
}

function save_state($s) {
    global $dataFile;
    $s['last_update'] = time();
    file_put_contents($dataFile, json_encode($s, JSON_PRETTY_PRINT));
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'register') {
    // register player: {name}
    $name = trim($_POST['name'] ?? '');
    if ($name === '') { echo json_encode(['ok'=>false,'msg'=>'name required']); exit; }
    $id = preg_replace('/[^a-z0-9_\-]/i','',strtolower($name)) . '_' . rand(1000,9999);
    global $state;
    $state['players'][$id] = ['id'=>$id,'name'=>$name,'buzzed'=>false,'last_buzz'=>0,'last_snapshot'=>0];
    save_state($state);
    echo json_encode(['ok'=>true,'id'=>$id,'state'=>$state]);
    exit;
}

if ($action === 'set_question') {
    // master sets question text or playlist item
    $q = $_POST['question'] ?? '';
    $playlist = $_POST['playlist'] ?? null;
    if ($playlist !== null) {
        // expect JSON string or array
        if (!is_array($playlist)) $playlist = json_decode($playlist, true);
        $state['playlist'] = is_array($playlist) ? $playlist : $state['playlist'];
    }
    if ($q !== '') $state['current_question'] = $q;
    save_state($state);
    echo json_encode(['ok'=>true,'state'=>$state]);
    exit;
}

if ($action === 'get_state') {
    // returns whole state (for master) or limited (for player)
    $id = $_GET['id'] ?? null;
    if ($id && isset($state['players'][$id])) {
        // player view: return current question and his own status
        $me = $state['players'][$id];
        echo json_encode(['ok'=>true,'role'=>'player','me'=>$me,'question'=>$state['current_question'],'timestamp'=>time()]);
    } else {
        // master view: full state
        echo json_encode(['ok'=>true,'role'=>'master','state'=>$state,'timestamp'=>time()]);
    }
    exit;
}

if ($action === 'buzz') {
    // player buzzes
    $id = $_POST['id'] ?? '';
    if ($id === '' || !isset($state['players'][$id])) { echo json_encode(['ok'=>false]); exit; }
    $state['players'][$id]['buzzed'] = true;
    $state['players'][$id]['last_buzz'] = time();
    save_state($state);
    echo json_encode(['ok'=>true,'state'=>$state]);
    exit;
}

if ($action === 'release_buzz') {
    // master releases (or auto release)
    $id = $_POST['id'] ?? '';
    if ($id === '') { echo json_encode(['ok'=>false]); exit; }
    if (isset($state['players'][$id])) {
        $state['players'][$id]['buzzed'] = false;
        save_state($state);
        echo json_encode(['ok'=>true,'state'=>$state]);
    } else echo json_encode(['ok'=>false]);
    exit;
}

if ($action === 'upload_snapshot') {
    // receives a binary image (blob) or base64
    $id = $_POST['id'] ?? ($_GET['id'] ?? '');
    if ($id === '' || !isset($state['players'][$id])) { echo json_encode(['ok'=>false,'msg'=>'unknown id']); exit; }
    // try file upload first (multipart)
    if (!empty($_FILES['snapshot']['tmp_name'])) {
        $tmp = $_FILES['snapshot']['tmp_name'];
        $dest = $snapDir . '/' . $id . '.jpg';
        if (move_uploaded_file($tmp, $dest)) {
            $state['players'][$id]['last_snapshot'] = time();
            save_state($state);
            echo json_encode(['ok'=>true]);
        } else echo json_encode(['ok'=>false,'msg'=>'move failed']);
    } else {
        // try base64 POST
        $b64 = $_POST['b64'] ?? '';
        if ($b64) {
            // expected "data:image/jpeg;base64,...."
            if (preg_match('/^data:image\/(png|jpeg);base64,/', $b64)) {
                $b64 = preg_replace('/^data:image\/(png|jpeg);base64,/', '', $b64);
            }
            $data = base64_decode($b64);
            if ($data === false) { echo json_encode(['ok'=>false,'msg'=>'decode']); exit; }
            $dest = $snapDir . '/' . $id . '.jpg';
            file_put_contents($dest, $data);
            $state['players'][$id]['last_snapshot'] = time();
            save_state($state);
            echo json_encode(['ok'=>true]);
        } else echo json_encode(['ok'=>false,'msg'=>'no image']);
    }
    exit;
}

// fallback
echo json_encode(['ok'=>false,'msg'=>'unknown action']);
