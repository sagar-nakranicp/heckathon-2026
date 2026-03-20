<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/src/polyfills.php';
require_once dirname(__DIR__) . '/src/UploadError.php';

try {
    $bootstrap = require dirname(__DIR__) . '/src/bootstrap.php';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    $msg = $e->getMessage();
    $hint = '';
    if (str_contains($msg, 'pdo_mysql') || str_contains($msg, 'could not find driver')
        || str_contains($msg, 'SQLSTATE') || str_contains($msg, 'Access denied')
        || str_contains($msg, 'Unknown database')) {
        $v = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $hint = '<p><strong>MySQL / PHP:</strong></p><ul>'
            . '<li>Install matching PHP MySQL extension and restart Apache: '
            . '<pre>sudo apt install php' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '-mysql && sudo systemctl restart apache2</pre></li>'
            . '<li>Or use PHP 8.2 for Apache: <code>sudo a2dismod php7.4</code> then <code>sudo a2enmod php8.2</code> and restart.</li>'
            . '<li>Create DB <code>call_analyzer</code> and import <code>database/schema.sql</code> in phpMyAdmin.</li>'
            . '<li>Set <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code> in <code>.env</code>.</li>'
            . '<li>Diagnostic: open <code>/call-analyzer/php-check.php</code></li></ul>';
    }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Call Analyzer — setup error</title></head><body style="font-family:sans-serif;max-width:42rem;margin:2rem;">';
    echo '<h1>Setup error</h1><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>' . $hint;
    echo '</body></html>';
    exit;
}
/** @var array{config: array, pdo: PDO, root: string} $bootstrap */
$config = $bootstrap['config'];
$pdo = $bootstrap['pdo'];
$root = $bootstrap['root'];

$repo = new CallRepository($pdo);
$openai = new OpenAiClient(
    $config['openai_api_key'],
    $config['openai_chat_model'],
    $config['openai_whisper_model'],
);
$uploadDir = $config['storage_path'] . '/uploads';
$analyzer = new CallAnalyzerService($repo, $openai, $uploadDir);

$action = $_GET['action'] ?? 'dashboard';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST' && ($action === 'upload' || isset($_POST['upload']))) {
    @set_time_limit(0);
    try {
        UploadStorage::ensureUploadDir($uploadDir);
    } catch (Throwable $e) {
        $_SESSION['flash'] = $e->getMessage();
        Http::redirect('/?action=upload', $config);
    }

    /** @var list<array{name: string, type: string, tmp_name: string, error: int, size: int}> $fileList */
    $fileList = [];
    if (isset($_FILES['recordings']['name']) && is_array($_FILES['recordings']['name'])) {
        $n = count($_FILES['recordings']['name']);
        for ($i = 0; $i < $n; $i++) {
            $fileList[] = [
                'name' => (string) ($_FILES['recordings']['name'][$i] ?? ''),
                'type' => (string) ($_FILES['recordings']['type'][$i] ?? ''),
                'tmp_name' => (string) ($_FILES['recordings']['tmp_name'][$i] ?? ''),
                'error' => (int) ($_FILES['recordings']['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($_FILES['recordings']['size'][$i] ?? 0),
            ];
        }
    } elseif (isset($_FILES['recording']) && is_array($_FILES['recording'])) {
        $fileList[] = [
            'name' => (string) ($_FILES['recording']['name'] ?? ''),
            'type' => (string) ($_FILES['recording']['type'] ?? ''),
            'tmp_name' => (string) ($_FILES['recording']['tmp_name'] ?? ''),
            'error' => (int) ($_FILES['recording']['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($_FILES['recording']['size'] ?? 0),
        ];
    }

    if ($fileList === []) {
        $_SESSION['flash'] = 'No file uploaded.';
        Http::redirect('/?action=upload', $config);
    }

    $savedIds = [];
    $errParts = [];

    foreach ($fileList as $f) {
        $label = $f['name'] !== '' ? $f['name'] : 'file';
        if ($f['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errParts[] = $label . ': ' . UploadError::message($f['error']);
            continue;
        }
        if ($f['size'] <= 0 || $f['size'] > $config['upload_max_bytes']) {
            $errParts[] = $label . ': file too large (max '
                . round($config['upload_max_bytes'] / 1024 / 1024, 1) . ' MB).';
            continue;
        }
        $tmp = $f['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: 'application/octet-stream';
        if (!in_array($mime, $config['allowed_mime'], true)) {
            $errParts[] = $label . ': unsupported type (' . $mime . ').';
            continue;
        }
        $orig = basename($f['name'] !== '' ? $f['name'] : 'recording');
        $ext = pathinfo($orig, PATHINFO_EXTENSION);
        $safeExt = preg_match('/^[a-z0-9]{1,8}$/i', (string) $ext) ? strtolower((string) $ext) : 'bin';
        $stored = bin2hex(random_bytes(16)) . '.' . $safeExt;
        $dest = $uploadDir . '/' . $stored;

        $save = UploadStorage::saveFromTmp($tmp, $dest);
        if ($save !== true) {
            $errParts[] = $label . ': ' . $save;
            continue;
        }

        $row = $repo->insertPending($stored, $orig, $mime, $f['size']);
        $newId = (int) ($row['id'] ?? 0);
        if ($newId <= 0) {
            $errParts[] = $label . ': could not save to database.';
            continue;
        }
        $savedIds[] = $newId;
        if ($openai->hasKey()) {
            $analyzer->processCall($newId);
        } else {
            $repo->setStatus($newId, 'error', 'Set OPENAI_API_KEY in .env to transcribe and analyze.');
        }
    }

    $messages = [];
    if ($errParts !== []) {
        $messages[] = implode(' ', $errParts);
    }
    if ($savedIds !== []) {
        $messages[] = count($savedIds) === 1
            ? 'Recording saved and processed.'
            : count($savedIds) . ' recordings saved and processed.';
    }
    if ($savedIds === [] && $errParts === []) {
        $_SESSION['flash'] = 'Please choose at least one audio file.';
        Http::redirect('/?action=upload', $config);
    }
    if ($messages !== []) {
        $_SESSION['flash'] = implode(' ', $messages);
    }
    if (count($savedIds) === 1) {
        Http::redirect('/?action=view&id=' . $savedIds[0], $config);
    }
    Http::redirect('/?action=dashboard', $config);
}

if ($action === 'audio' && $method === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    $call = $id > 0 ? $repo->find($id) : null;
    if ($call === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        exit;
    }
    $stored = (string) ($call['stored_filename'] ?? '');
    if ($stored === '' || strpos($stored, '..') !== false || strpos($stored, '/') !== false) {
        http_response_code(400);
        exit;
    }
    $path = $uploadDir . '/' . $stored;
    $baseReal = realpath($uploadDir);
    $fileReal = realpath($path);
    if ($baseReal === false || $fileReal === false || strpos($fileReal, $baseReal) !== 0 || !is_readable($fileReal)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'File not available';
        exit;
    }
    $mime = (string) ($call['mime'] ?? 'audio/mpeg');
    if (!in_array($mime, $config['allowed_mime'], true)) {
        $mime = 'application/octet-stream';
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($fileReal));
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($fileReal);
    exit;
}

if ($action === 'analyze' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid id']);
        exit;
    }
    echo json_encode($analyzer->processCall($id));
    exit;
}

if ($action === 'view') {
    $id = (int) ($_GET['id'] ?? 0);
    $call = $id > 0 ? $repo->find($id) : null;
    if ($call === null) {
        http_response_code(404);
        $pageTitle = 'Not found';
        require $root . '/views/layout_header.php';
        echo '<p class="muted">Call not found.</p>';
        require $root . '/views/layout_footer.php';
        exit;
    }
    $analysis = null;
    if (!empty($call['analysis_json'])) {
        $analysis = json_decode((string) $call['analysis_json'], true);
        if (!is_array($analysis)) {
            $analysis = null;
        }
    }
    $hasOpenAiKey = $openai->hasKey();
    $storedName = (string) ($call['stored_filename'] ?? '');
    $audioPath = $storedName !== '' && strpos($storedName, '..') === false
        ? $uploadDir . '/' . $storedName
        : '';
    $hasAudio = $audioPath !== '' && is_file($audioPath);
    if ($hasAudio) {
        $ds = isset($call['duration_seconds']) ? (float) $call['duration_seconds'] : 0.0;
        if ($ds <= 0) {
            $guessDur = AudioDuration::guess($audioPath);
            if ($guessDur !== null) {
                $repo->saveDuration($id, $guessDur);
                $call = $repo->find($id) ?? $call;
            }
        }
    }
    $avgCallDurationSeconds = $repo->averageDurationSeconds();
    $origTitle = basename((string) ($call['original_name'] ?? ''));
    $pageTitle = $origTitle !== '' ? $origTitle : ('Call #' . $id);
    if (strlen($pageTitle) > 72) {
        $pageTitle = substr($pageTitle, 0, 69) . '...';
    }
    require $root . '/views/layout_header.php';
    require $root . '/views/call_detail.php';
    require $root . '/views/layout_footer.php';
    exit;
}

if ($action === 'help') {
    $pageTitle = 'How it works';
    $hasOpenAiKey = $openai->hasKey();
    require $root . '/views/layout_header.php';
    require $root . '/views/help.php';
    require $root . '/views/layout_footer.php';
    exit;
}

if ($action === 'upload') {
    $pageTitle = 'Upload recording';
    require $root . '/views/layout_header.php';
    require $root . '/views/upload.php';
    require $root . '/views/layout_footer.php';
    exit;
}

$calls = $repo->listRecent(100);
$counts = $repo->countsByStatus();
$avg = $repo->averageScore();
$avgDuration = $repo->averageDurationSeconds();
$hasOpenAiKey = $openai->hasKey();
$pageTitle = 'Dashboard';
require $root . '/views/layout_header.php';
require $root . '/views/dashboard.php';
require $root . '/views/layout_footer.php';
