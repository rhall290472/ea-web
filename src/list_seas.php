<?php
// Force JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Optional: only if CORS ever becomes an issue

// Read all SEA files
$files = glob('../data/sea-*.json');
$seas = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) continue;
    
    $data = json_decode($content, true);
    if (!$data) continue;
    
    $id = basename($file, '.json');
    $id = str_replace('sea-', '', $id);
    
    $data['id'] = $id;
    $seas[] = $data;
}

// Sort by timestamp (newest first)
usort($seas, function($a, $b) {
    return strtotime($b['timestamp'] ?? '') - strtotime($a['timestamp'] ?? '');
});

echo json_encode($seas, JSON_PRETTY_PRINT);
?>