<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if ($role !== 'seeker') {
    header('Location: jobs.php');
    exit();
}

$jobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
$returnTo = isset($_POST['return_to']) ? trim((string) $_POST['return_to']) : '';

if ($jobId <= 0) {
    header('Location: jobs.php');
    exit();
}

$existsStmt = $conn->prepare('SELECT 1 FROM saved_jobs WHERE job_id = ? AND user_id = ? LIMIT 1');
$alreadySaved = false;
if ($existsStmt) {
    $existsStmt->bind_param('ii', $jobId, $userId);
    $existsStmt->execute();
    $res = $existsStmt->get_result();
    $alreadySaved = $res && $res->num_rows > 0;
    $existsStmt->close();
}

$changedTo = $alreadySaved ? 'removed' : 'saved';

if ($alreadySaved) {
    $del = $conn->prepare('DELETE FROM saved_jobs WHERE job_id = ? AND user_id = ?');
    if ($del) {
        $del->bind_param('ii', $jobId, $userId);
        $del->execute();
        $del->close();
    }
} else {
    $ins = $conn->prepare('INSERT INTO saved_jobs (job_id, user_id) VALUES (?, ?)');
    if ($ins) {
        $ins->bind_param('ii', $jobId, $userId);
        $ins->execute();
        $ins->close();
    }
}

$isJson = false;
if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    $isJson = true;
}
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $isJson = true;
}

if ($isJson) {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'status' => $changedTo,
        'job_id' => $jobId,
    ]);
    exit();
}

if ($returnTo !== '') {
    $parsed = parse_url($returnTo);
    $hasScheme = is_array($parsed) && isset($parsed['scheme']);
    $hasHost = is_array($parsed) && isset($parsed['host']);
    $path = is_array($parsed) && isset($parsed['path']) ? $parsed['path'] : '';

    if (!$hasScheme && !$hasHost && is_string($path) && $path !== '' && substr($path, 0, 1) === '/') {
        header('Location: ' . $returnTo);
        exit();
    }
}

$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref !== '' && strpos($ref, 'http') === 0) {
    header('Location: ' . $ref);
    exit();
}

header('Location: saved-jobs.php');
exit();
