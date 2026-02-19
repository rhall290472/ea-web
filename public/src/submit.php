<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$customLogFile = __DIR__ . '/mpdf-errors.log';  // Creates mpdf-errors.log INSIDE /src/

ini_set('log_errors', 1);
ini_set('error_log', $customLogFile);

// src/submit.php
require_once __DIR__ . '/../../config.php';
if($is_localhost)
  require_once $_SERVER['DOCUMENT_ROOT'] . '/ea-web/vendor/autoload.php';
else
require_once $_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php';

use Mpdf\Mpdf;

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'sea_id' => ''];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Invalid request method');
  }

  $action = $_POST['action'] ?? 'create';
  $sea_id = $_POST['sea_id'] ?? '';

  $fleet = strtoupper(trim($_POST['fleet'] ?? 'UNKNOWN'));
  $fleet = preg_replace('/[^A-Z0-9]/', '', $fleet); // Sanitize: only uppercase letters/numbers
  if ($fleet === '') $fleet = 'UNKNOWN';

  if ($action === 'create' && empty($sea_id)) {
    $datePart = date('Ymd');
    $randomPart = substr(str_shuffle('0123456789'), 0, 3);

    $short_id = "{$fleet}-{$datePart}-{$randomPart}";
    $sea_id = "sea-{$short_id}"; // Full ID for filename only
  } else {
    $short_id = $sea_id;
  }

  $jsonFile = DATA_DIR . '/sea-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $short_id) . '.json';
  $existing = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

  $sea = [
    'id'             => $short_id, // Always store short ID without 'sea-'
    'requester'      => $_POST['requester'] ?? $existing['requester'] ?? '',
    'description'    => $_POST['description'] ?? $existing['description'] ?? '',
    'justification'  => $_POST['justification'] ?? $existing['justification'] ?? '',
    'impact'         => $_POST['impact'] ?? $existing['impact'] ?? '',
    'priority'       => $_POST['priority'] ?? $existing['priority'] ?? 'Medium',
    'target_date'    => $_POST['target_date'] ?? $existing['target_date'] ?? '',
    'timestamp'      => date('Y-m-d H:i:s'),
    'status'         => $_POST['status'] ?? $existing['status'] ?? 'Planning',
    'version'        => ($existing['version'] ?? 0) + 1,
    'parts_json'     => $_POST['parts_json'] ?? $existing['parts_json'] ?? '[]',
    'instructions_json' => $_POST['instructions_json'] ?? $existing['instructions_json'] ?? '[]',
    'ea_number'      => $_POST['ea_number'] ?? $existing['ea_number'] ?? '',
    'revision'       => $_POST['revision'] ?? $existing['revision'] ?? '',
    'fleet'          => $fleet ?? $existing['fleet'] ?? '',
    'device'         => is_array($_POST['device']) ? array_filter($_POST['device']) : ($existing['device'] ?? [])
  ];

  $uploadDir = UPLOAD_DIR . '/SEA/' . $short_id;
  $webBase   = '/data/uploads/SEA/' . $short_id;

  if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    throw new Exception('Failed to create upload directory');
  }

  $keepAttachments = [];
  if (!empty($_POST['keep_attachments']) && is_array($_POST['keep_attachments'])) {
    foreach ($_POST['keep_attachments'] as $url) {
      if (strpos($url, $webBase) === 0) {
        $keepAttachments[] = $url;
      }
    }
  }

  $newAttachments = [];
  $conflicts = [];

  if (!empty($_FILES['attachments']['name'][0]) && is_array($_FILES['attachments']['name'])) {
    foreach ($_FILES['attachments']['name'] as $k => $name) {
      if ($_FILES['attachments']['error'][$k] !== UPLOAD_ERR_OK || empty($name)) {
        continue;
      }

      // Sanitize once
      $safeName = preg_replace('/[^\w\.\-]/', '_', $name);
      $targetPath = $uploadDir . '/' . $safeName;
      $webUrl = $webBase . '/' . $safeName;

      // Check for conflict
      if (file_exists($targetPath)) {
        if (!empty($_POST['overwrite']) && is_array($_POST['overwrite'])) {
          // User confirmed overwrite for this file
          if (in_array($safeName, $_POST['overwrite'])) {
            // Proceed with overwrite
          } else {
            $conflicts[] = $safeName;
            continue;
          }
        } else {
          $conflicts[] = $safeName;
          continue;
        }
      }

      // Move with error handling
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

  // if (file_put_contents($jsonFile, json_encode($sea, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
  //   throw new Exception('Failed to save SEA data');
  // }
  $jsonData = json_encode($sea, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

  if ($jsonData === false) {
    throw new Exception("JSON encoding failed: " . json_last_error_msg());
  }

  $bytes = file_put_contents($jsonFile, $jsonData);

  if ($bytes === false) {
    $errorMessage = "Failed to write file\n\n";

    $errorMessage .= "Target path:\n" . $jsonFile . "\n\n";

    $errorMessage .= "Directory exists?  → " . (is_dir(dirname($jsonFile)) ? 'YES' : '**NO**') . "\n";
    $errorMessage .= "Directory writable? → " . (is_writable(dirname($jsonFile)) ? 'YES' : '**NO**') . "\n";

    if (is_dir(dirname($jsonFile))) {
      $errorMessage .= "Realpath of directory: " . realpath(dirname($jsonFile)) . "\n";
    }

    if (file_exists($jsonFile)) {
      $errorMessage .= "File already exists but is not writable → " . (is_writable($jsonFile) ? 'writable' : '**NOT writable**') . "\n";
    }

    $errorMessage .= "\nPHP error (if any):\n" . error_get_last()['message'] ?? '(no error_get_last info)';

    throw new Exception($errorMessage);
  }

  ob_end_clean();

  $response = [
    'success' => true,
    'message' => ($action === 'create' ? 'SEA created' : 'SEA updated') . ' successfully!',
    'sea_id' => $sea['id'],
    'pdf' => base64_encode($pdfContent ?? ''),
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
