<?php
// src/upload_image.php
header('Content-Type: application/json');

// The sea_id comes from the frontend (temp-xxx or real ID)
$sea_id = $_POST['sea_id'] ?? 'temp-' . time();

// Use the folder that matches what the frontend is currently using
$upload_dir = "../data/uploads/SEA/{$sea_id}";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (!isset($_FILES['file'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
$filepath = $upload_dir . '/' . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // This EXACT path must match what submit.php expects to replace
    $url = "data/uploads/SEA/{$sea_id}/{$filename}";
    echo json_encode(['location' => $url]);
} else {
    echo json_encode(['error' => 'Failed to save file']);
}
?>