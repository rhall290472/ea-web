<?php
// src/list_seas.php
require_once __DIR__ . '/../config.php';

$files = glob(DATA_DIR . '/sea-*.json');
$seas = [];

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);
    if ($data) {
        $data['_filename'] = basename($file);
        $seas[] = $data;
    }
}

// Sort newest first
usort($seas, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));

header('Content-Type: application/json');
echo json_encode($seas);