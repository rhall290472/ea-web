<?php
// src/upload_image.php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

$response = ['location' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file']) || empty($_POST['sea_id'])) {
        throw new Exception('Invalid request');
    }

    $sea_id = preg_replace('/[^A-Za-z0-9\-]/', '-', $_POST['sea_id']);
    if ($sea_id === '') {
        throw new Exception('No SEA ID');
    }

    $uploadDir = UPLOAD_DIR . '/SEA/' . $sea_id;
    $webBase   = '/data/uploads/SEA/' . $sea_id;

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Failed to create upload directory');
    }

    // ────────────────────────────────────────────────
    //  Unique filename – this is the important change
    // ────────────────────────────────────────────────
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
        throw new Exception('Only image files allowed (jpg, png, gif, webp, bmp)');
    }

    // Format: 20260220-113745-4a8b9c2d.png
    $unique = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $safeName = $unique . '.' . $ext;

    $targetPath = $uploadDir . '/' . $safeName;
    $webUrl     = $webBase   . '/' . $safeName;
    // ────────────────────────────────────────────────

    if (getimagesize($_FILES['file']['tmp_name']) === false) {
        throw new Exception('Not a valid image');
    }

    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        $response['location'] = $webUrl;
    } else {
        throw new Exception('Failed to upload image');
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => $e->getMessage()];
}

echo json_encode($response);
exit;