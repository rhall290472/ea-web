<?php
// src/upload_image.php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

$response = ['location' => ''];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file']) || empty($_POST['sea_id'])) {
    throw new Exception('Invalid request');
  }

  $sea_id = preg_replace('/[^A-Za-z0-9\-]/', '-', $_POST['sea_id']);  // Sanitize
  if ($sea_id === '') {
    throw new Exception('No SEA ID');
  }

  $uploadDir = UPLOAD_DIR . '/SEA/' . $sea_id;
  $webBase = '/data/uploads/SEA/' . $sea_id;

  if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    throw new Exception('Failed to create upload directory');
  }

  $name = $_FILES['file']['name'];
  $safeName = preg_replace('/[^\w\.\-]/', '_', $name);  // Sanitize filename
  $targetPath = $uploadDir . '/' . $safeName;

  // Optional: Check if image (add more validation in production)
  if (getimagesize($_FILES['file']['tmp_name']) === false) {
    throw new Exception('Not a valid image');
  }

  if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
    $response['location'] = $webBase . '/' . $safeName;
  } else {
    throw new Exception('Failed to upload image');
  }
} catch (Exception $e) {
  http_response_code(500);
  $response = ['error' => $e->getMessage()];
}

echo json_encode($response);
exit;
