<?php
// src/list_seas.php
require_once __DIR__ . '/../../config.php';

// Simple h() function (safe output)
if (!function_exists('h')) {
  function h($str)
  {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
  }
}

$files = glob(DATA_DIR . '/sea-*.json');
$seas = [];

foreach ($files as $file) {
  $content = file_get_contents($file);
  if ($content === false) continue;
  $data = json_decode($content, true);
  if (!$data) continue;

  $id = basename($file, '.json');
  $id = str_replace('sea-', '', $id);
  $data['id'] = $id;
  $data['_filename'] = basename($file);
  $seas[] = $data;
}

// Sort newest first
usort($seas, fn($a, $b) => (strtotime($b['timestamp'] ?? '') <=> strtotime($a['timestamp'] ?? '')));

echo '<div class="row" id="seaContainer">';

if (empty($seas)) {
  echo '<div class="col-12"><div class="alert alert-info text-center">
        No SEAs found. <a href="#" onclick="showCreateForm()">Create one</a>.
    </div></div>';
} else {
  foreach ($seas as $s) {
    $dev = is_array($s['device']) ? implode(', ', $s['device']) : ($s['device'] ?? '—');
    $status = $s['status'] ?? 'Submitted';
    $badge = $status === 'Approved' ? 'success' : ($status === 'Rejected' ? 'danger' : 'warning');

    // Start heredoc (no <?=? inside)
    echo <<<CARD
        <div class="col-md-6 mb-3">
            <div class="card sea-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>SEA-{$s['id']}</strong>
                    <span class="badge bg-{$badge}">{$status}</span>
                </div>
                <div class="card-body">
                    <p><strong>EA#:</strong> {$s['ea_number']}</p>
CARD;

// === DESCRIPTION: Click to expand (SAFE & WORKING) ===
$desc = $s['description'] ?? '';
$shortDesc = strlen($desc) > 120 ? substr($desc, 0, 120) . '...' : $desc;

echo '<p class="mb-2"><strong>Title:</strong> ';
echo '<span class="text-muted desc-short" ';
echo 'data-full="' . h($desc) . '" ';  // Full text stored safely
echo 'data-short="' . h($shortDesc) . '" ';  // Short version
echo 'style="max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:inline-block;">';
echo h($shortDesc);
echo '</span>';

if (strlen($desc) > 120) {
    echo ' <a href="#" class="small text-primary expand-desc" data-target="desc-' . $s['id'] . '">[show more]</a>';
}
echo '</p>';

// Continue heredoc
    echo <<<CARD
                    <p><strong>Fleet:</strong> {$s['fleet']} | <strong>Device:</strong> {$dev}</p>
                    <p><strong>Requester:</strong> {$s['requester']}</p>
                    <p class="small text-muted">{$s['timestamp']} | v{$s['version']}</p>
                    <div class="mt-2 btn-group w-100">
                        <button class="btn btn-sm btn-primary" onclick="editSea('{$s['id']}')">
                            Edit
                        </button>
                        <a href="./src/print_sea.php?id={$s['id']}" target="_blank" 
                           class="btn btn-sm btn-success">PDF</a>
                        <a href="../data/sea-{$s['id']}.json" download 
                           class="btn btn-sm btn-outline-primary">JSON</a>
                        <button class="btn btn-sm btn-outline-danger" 
                                onclick="deleteSea('{$s['id']}')">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
CARD;
  }
}

echo '</div>';
