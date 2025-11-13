<?php
// src/submit.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../inc/path_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

ob_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'sea_id' => ''];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Invalid request method');
  }

  $action = $_POST['action'] ?? 'create';
  $sea_id = $_POST['sea_id'] ?? '';  // For create, this will be 'temp-xxx'; for edit, the actual ID

  $fleet = strtoupper(trim($_POST['fleet'] ?? 'UNKNOWN'));
  $fleet = preg_replace('/[^A-Z0-9]/', '', $fleet);
  if ($fleet === '') $fleet = 'UNKNOWN';

  // --- GENERATE NEW SHORT ID IF CREATING ---
  if ($action === 'create') {
    $datePart = date('Ymd');
    $randomPart = substr(str_shuffle('0123456789'), 0, 3);
    $short_id = "{$fleet}-{$datePart}-{$randomPart}";
  } else {
    $short_id = $sea_id;  // For edit, use provided ID
  }

  $jsonFile = DATA_DIR . '/sea-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $short_id) . '.json';
  $existing = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

  // --- BUILD SEA DATA ---
  $sea = [
    'id'             => $short_id,
    'requester'      => $_POST['requester'] ?? $existing['requester'] ?? '',
    'description'    => $_POST['description'] ?? $existing['description'] ?? '',
    'justification'  => $_POST['justification'] ?? $existing['justification'] ?? '',
    'impact'         => $_POST['impact'] ?? $existing['impact'] ?? '',
    'priority'       => $_POST['priority'] ?? $existing['priority'] ?? 'Medium',
    'target_date'    => $_POST['target_date'] ?? $existing['target_date'] ?? '',
    'revision'       => $_POST['revision'] ?? $existing['revision'] ?? '',
    'status'         => $_POST['status'] ?? $existing['status'] ?? 'Planning',
    'fleet'          => $fleet,
    'device'         => is_array($_POST['device']) ? array_filter($_POST['device']) : ($existing['device'] ?? []),
    'ea_number'      => $_POST['ea_number'] ?? $existing['ea_number'] ?? '',
    'version'        => ($action === 'create') ? '1' : (($existing['version'] ?? 0) + 1),
    'timestamp'      => date('Y-m-d H:i:s')
  ];

  // --- PARTS TABLE ---
  $sea['parts_json'] = $_POST['parts_json'] ?? '[]';

  // --- INSTRUCTIONS (TinyMCE) ---
  $sea['instructions_json'] = $_POST['instructions_json'] ?? '[]';

  // --- RENAME TEMP FOLDER & FIX IMAGE URLS (ONLY ON CREATE) ---
  $keepAttachments = [];
  if ($action === 'create') {
    $oldId = $sea_id;  // 'temp-xxx'
    $newId = $short_id;
    $oldDir = UPLOAD_DIR . '/SEA/' . $oldId;
    $newDir = UPLOAD_DIR . '/SEA/' . $newId;

    if (strpos($oldId, 'temp-') === 0 && is_dir($oldDir)) {
      if (!rename($oldDir, $newDir)) {
        throw new Exception('Failed to rename upload directory');
      }
    }

    $oldBase = "data/uploads/SEA/{$oldId}";
$newBase = "data/uploads/SEA/{$newId}";

    // Fix URLs in instructions (for embedded images)
    $instructions = json_decode($sea['instructions_json'], true) ?? [];
    foreach ($instructions as &$inst) {
      if (isset($inst['instruction']) && is_string($inst['instruction'])) {
        $inst['instruction'] = str_replace($oldBase, $newBase, $inst['instruction']);
      }
    }
    $sea['instructions_json'] = json_encode($instructions);

    // Fix keep_attachments if any (though rare for new)
    if (!empty($_POST['keep_attachments']) && is_array($_POST['keep_attachments'])) {
      foreach ($_POST['keep_attachments'] as $url) {
        $keepAttachments[] = str_replace($oldBase, $newBase, $url);
      }
    }
  } else {
    // For edit: no rename needed
    if (!empty($_POST['keep_attachments']) && is_array($_POST['keep_attachments'])) {
      $keepAttachments = $_POST['keep_attachments'];
    }
  }

  // --- ATTACHMENTS (NEW FILES) ---
  $newAttachments = [];
  $conflicts = [];
  $overwrite = $_POST['overwrite'] ?? [];

  if (!empty($_FILES['attachments']['name'][0])) {
    $uploadDir = UPLOAD_DIR . '/SEA/' . $short_id;
    $webBase = getWebBaseUrl($short_id);

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
      throw new Exception('Failed to create upload directory');
    }

    for ($k = 0; $k < count($_FILES['attachments']['name']); $k++) {
      $name = $_FILES['attachments']['name'][$k];
      if (empty($name)) continue;

      $safeName = preg_replace('/[^\w\.\-]/', '_', $name);
      $targetPath = $uploadDir . '/' . $safeName;
      $webUrl = $webBase . '/' . $safeName;

      if (file_exists($targetPath)) {
        if (!in_array($safeName, $overwrite)) {
          $conflicts[] = $safeName;
          continue;
        }
      }

      if (move_uploaded_file($_FILES['attachments']['tmp_name'][$k], $targetPath)) {
        $newAttachments[] = $webUrl;
      } else {
        throw new Exception('Failed to upload file: ' . $name);
      }
    }
  }

  if (!empty($conflicts)) {
    ob_end_clean();
    $response = [
      'success' => false,
      'conflict' => true,
      'conflicting_files' => array_unique($conflicts),
      'message' => 'File' . (count($conflicts) > 1 ? 's' : '') . ' already exist: ' . implode(', ', $conflicts) . '. Overwrite?'
    ];
    echo json_encode($response);
    exit;
  }

  $sea['attachments'] = array_merge($keepAttachments, $newAttachments);

  // --- SAVE JSON ---
  $jsonTest = json_encode($sea, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('JSON encode error: ' . json_last_error_msg());
  }

  if (file_put_contents($jsonFile, $jsonTest) === false) {
    throw new Exception("Failed to write to $jsonFile - check permissions");
  }

  ob_end_clean();

  $response = [
    'success' => true,
    'message' => ($action === 'create' ? 'SEA created' : 'SEA updated') . ' successfully!',
    'sea_id' => $sea['id'],
    'pdf' => '', // You can re-enable PDF later
    'pdf_name' => 'SEA-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $sea['id']) . '.pdf'
  ];
} catch (Exception $e) {
  ob_end_clean();
  $response['message'] = 'Error: ' . $e->getMessage();
} catch (Error $e) {
  ob_end_clean();
  $response['message'] = 'Server error: ' . $e->getMessage();
}

echo json_encode($response);
exit;