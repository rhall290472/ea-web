<?php
// src/delete.php
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['filename'])) {
    header('Location: view.php');
    exit;
}

$filename = $_POST['filename'];
$filepath = DATA_DIR . '/' . $filename;

if (file_exists($filepath)) {
    $data = json_decode(file_get_contents($filepath), true);
    $SEA_id = $data['id'] ?? 'unknown';

    // Delete main JSON
    unlink($filepath);

    // Delete uploads folder
    $uploadDir = UPLOAD_DIR . '/' . $SEA_id;
    if (is_dir($uploadDir)) {
        array_map('unlink', glob($uploadDir . '/*'));
        rmdir($uploadDir);
    }

    // Delete history
    $historyDir = DATA_DIR . '/history/' . $SEA_id;
    if (is_dir($historyDir)) {
        array_map('unlink', glob($historyDir . '/*'));
        rmdir($historyDir);
    }
}

header('Location: view.php?deleted=1');
exit;