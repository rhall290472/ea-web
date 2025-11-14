<?php
// src/submit.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../inc/path_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

// === ALLOW AJAX REQUESTS ===
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');


//Debug
error_log("POST data: " . print_r($_POST, true));  // Logs to error_log
error_log("FILES data: " . print_r($_FILES, true));




// === BYPASS mod_security / 406 ===
if (
  !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
  http_response_code(406);
  exit(json_encode(['success' => false, 'message' => 'Invalid request']));
}

// === RESPONSE HOLDER ===
$response = ['success' => false, 'message' => '', 'sea_id' => ''];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Invalid request method');
  }

  $action = $_POST['action'] ?? 'create';
  $sea_id = $_POST['sea_id'] ?? '';

  // === VALIDATE FLEET ===
  $fleet = strtoupper(trim($_POST['fleet'] ?? 'UNKNOWN'));
  $fleet = preg_replace('/[^A-Z0-9]/', '', $fleet);
  if ($fleet === '') $fleet = 'UNKNOWN';

  // === GENERATE SHORT ID (only on create) ===
  if ($action === 'create') {
    $datePart = date('Ymd');
    $randomPart = substr(str_shuffle('0123456789'), 0, 3);
    $short_id = "{$fleet}-{$datePart}-{$randomPart}";
  } else {
    $short_id = $sea_id;
  }

  $jsonFile = DATA_DIR . '/sea-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $short_id) . '.json';
  $existing = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

  // === BUILD SEA DATA ===
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

  // === PARTS & INSTRUCTIONS (from individual fields) ===
  $parts = [];
  foreach ($_POST['parts'] ?? [] as $p) {
    $parts[] = [
      'part' => $p['part'] ?? '',
      'desc' => $p['desc'] ?? '',
      'type' => $p['type'] ?? '',
      'qty'  => $p['qty']  ?? ''
    ];
  }
  $sea['parts_json'] = json_encode($parts);

  $instructions = [];
  foreach ($_POST['instructions'] ?? [] as $i) {
    $instructions[] = [
      'instruction' => $i['instruction'] ?? '',
      'notes'       => $i['notes']       ?? ''
    ];
  }
  $sea['instructions_json'] = json_encode($instructions);

  // === ATTACHMENTS: Keep existing + upload new ===
  $keepAttachments = $_POST['keep_attachments'] ?? [];
  $newAttachments = [];
  $conflicts = [];
  $overwrite = $_POST['overwrite'] ?? [];

  $uploadDir = UPLOAD_DIR . '/SEA/' . $short_id;
  $webBase = getWebBaseUrl($short_id);

  if (!empty($_FILES['attachments']['name'][0])) {
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
        throw new Exception("Failed to upload: $name");
      }
    }
  }

  // === CONFLICT HANDLING ===
  if (!empty($conflicts)) {
    ob_end_clean();
    echo json_encode([
      'success' => false,
      'conflict' => true,
      'conflicting_files' => array_unique($conflicts),
      'message' => 'File(s) already exist: ' . implode(', ', $conflicts)
    ]);
    exit;
  }

  $sea['attachments'] = array_merge($keepAttachments, $newAttachments);

  // === SAVE JSON ===
  $jsonTest = json_encode($sea, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('JSON encode error: ' . json_last_error_msg());
  }

  if (file_put_contents($jsonFile, $jsonTest) === false) {
    throw new Exception("Failed to write JSON file - check permissions");
  }

  // === SUCCESS ===
  // === SUCCESS: REDIRECT BACK TO APP (NON-AJAX) ===
  $redirectUrl = '../index.html?id=' . $short_id . '&msg=success';
  header('Location: ' . $redirectUrl);
  exit;  // Stop PHP execution


} catch (Exception $e) {
  ob_end_clean();
  $response['message'] = 'Error: ' . $e->getMessage();
} catch (Error $e) {
  ob_end_clean();
  $response['message'] = 'Server error: ' . $e->getMessage();
}
