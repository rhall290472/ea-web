<?php
// src/submit.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

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

  if ($action === 'create' && empty($sea_id)) {
    $fleet = strtoupper(trim($_POST['fleet'] ?? 'UNKNOWN'));
    $fleet = preg_replace('/[^A-Z0-9]/', '', $fleet); // Sanitize: only uppercase letters/numbers
    if ($fleet === '') $fleet = 'UNKNOWN';

    $datePart = date('Ymd');
    $randomPart = substr(str_shuffle('0123456789'), 0, 3);

    $sea_id = "SEA-{$fleet}-{$datePart}-{$randomPart}";
  }

  $jsonFile = DATA_DIR . '/sea-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $sea_id) . '.json';
  $existing = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

  $sea = [
    'id'             => $sea_id,
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
    'fleet'          => $_POST['fleet'] ?? $existing['fleet'] ?? '',
    'device'         => is_array($_POST['device']) ? array_filter($_POST['device']) : []
  ];

  $uploadDir = UPLOAD_DIR . '/SEA/' . $sea_id;
  $webBase   = '/data/uploads/SEA/' . $sea_id;

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

  if (file_put_contents($jsonFile, json_encode($sea, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    throw new Exception('Failed to save SEA data');
  }

  // $pdfContent = null;
  // $pdfError = '';
  // try {
  //     ob_clean();

  //     function h($s)
  //     {
  //         return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
  //     }
  //     function nl2br_h($s)
  //     {
  //         return nl2br(h($s));
  //     }

  //     $parts = json_decode($sea['parts_json'], true) ?? [];
  //     $partsHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
  //         <thead><tr style="background:#f8f9fa;">
  //             <th style="border:1px solid #ddd;padding:8px;">Part</th>
  //             <th style="border:1px solid #ddd;padding:8px;">Desc</th>
  //             <th style="border:1px solid #ddd;padding:8px;">Type</th>
  //             <th style="border:1px solid #ddd;padding:8px;text-align:center;">Qty</th>
  //         </tr></thead><tbody>';
  //     foreach ($parts as $p) {
  //         $partsHtml .= "<tr>
  //             <td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['part'] ?? '') . "</td>
  //             <td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['desc'] ?? '') . "</td>
  //             <td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['type'] ?? '') . "</td>
  //             <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\">" . h($p['qty'] ?? '') . "</td>
  //         </tr>";
  //     }
  //     $partsHtml .= '</tbody></table>';
  //     if (empty($parts)) $partsHtml = '<p style="font-style:italic;color:#666;margin:15px 0;">None</p>';

  //     $inst = json_decode($sea['instructions_json'], true) ?? [];
  //     $instHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
  //         <thead><tr style="background:#f8f9fa;">
  //             <th style="border:1px solid #ddd;padding:8px;width:8%;">#</th>
  //             <th style="border:1px solid #ddd;padding:8px;">Instruction</th>
  //             <th style="border:1px solid #ddd;padding:8px;">Notes</th>
  //         </tr></thead><tbody>';
  //     foreach ($inst as $i => $row) {
  //         $instHtml .= "<tr>
  //             <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\">" . ($i + 1) . "</td>
  //             <td style=\"border:1px solid #ddd;padding:8px;\">" . nl2br_h($row['instruction'] ?? '') . "</td>
  //             <td style=\"border:1px solid #ddd;padding:8px;\">" . h($row['notes'] ?? '') . "</td>
  //         </tr>";
  //     }
  //     $instHtml .= '</tbody></table>';
  //     if (empty($inst)) $instHtml = '<p style="font-style:italic;color:#666;margin:15px 0;">None</p>';

  //     $deviceDisplay = !empty($sea['device']) ? h(implode(', ', is_array($sea['device']) ? $sea['device'] : [$sea['device']])) : '—';

  //     $attachHtml = '';
  //     if (!empty($sea['attachments']) && is_array($sea['attachments'])) {
  //         $attachHtml = '<h2 style="margin-top:30px;font-size:14px;">Attachments</h2>
  //             <ul style="font-size:11px;margin:10px 0 20px 20px;">';
  //         foreach ($sea['attachments'] as $url) {
  //             $name = basename($url);
  //             $fullUrl = strpos($url, 'http') === 0 ? $url : 'http://' . $_SERVER['HTTP_HOST'] . $url;
  //             $attachHtml .= "<li><a href=\"{$fullUrl}\" target=\"_blank\">" . h($name) . "</a></li>";
  //         }
  //         $attachHtml .= '</ul>';
  //     }

  //     $html = <<<HTML
  //     <!DOCTYPE html><html><head><meta charset="UTF-8"><style>
  //         body {font-family:Arial,Helvetica,sans-serif;margin:30px;font-size:12px;line-height:1.5;}
  //         h1 {text-align:center;color:#2c3e50;margin-bottom:5px;}
  //         h2 {font-size:14px;color:#2c3e50;margin:25px 0 10px 0;}
  //         .label {font-weight:bold;display:inline-block;width:150px;color:#444;}
  //         p {margin:8px 0;}
  //         table {width:100%;border-collapse:collapse;margin:15px 0;}
  //         th, td {border:1px solid #ddd;padding:8px;font-size:11px;}
  //         th {background:#f8f9fa;text-align:left;}
  //         a {color:#0066cc;text-decoration:underline;}
  //         .generated {text-align:center;color:#666;font-size:10px;margin-top:20px;}
  //     </style></head><body>
  //         <h1>Simulator Engineering Authorization (SEA)</h1>
  //         <p class="generated"><strong>Generated:</strong> {{DATE}}</p>
  //         <p><span class="label">SEA ID:</span> {{SEA_ID}}</p>
  //         <p><span class="label">EA#:</span> {{EA_NUMBER}}</p>
  //         <p><span class="label">Revision:</span> {{REVISION}}</p>
  //         <p><span class="label">Fleet:</span> {{FLEET}}</p>
  //         <p><span class="label">Device(s):</span> {{DEVICES}}</p>
  //         <p><span class="label">Requester:</span> {{REQUESTER}}</p>
  //         <p><span class="label">Description:</span><br>{{DESCRIPTION}}</p>
  //         <p><span class="label">Justification:</span><br>{{JUSTIFICATION}}</p>
  //         <p><span class="label">Impact:</span><br>{{IMPACT}}</p>
  //         <p><span class="label">Priority:</span> {{PRIORITY}}</p>
  //         <p><span class="label">Target Date:</span> {{TARGET_DATE}}</p>
  //         <h2>Affected Parts</h2>{{PARTS_TABLE}}
  //         <h2>Work Instructions</h2>{{INSTRUCTIONS_TABLE}}
  //         {{ATTACHMENTS}}
  //     </body></html>
  //     HTML;

  //     $replacements = [
  //         '{{DATE}}' => date('Y-m-d H:i:s'),
  //         '{{SEA_ID}}' => h($sea['id'] ?? ''),
  //         '{{EA_NUMBER}}' => h($sea['ea_number'] ?? '—'),
  //         '{{REVISION}}' => h($sea['revision'] ?? '—'),
  //         '{{FLEET}}' => h($sea['fleet'] ?? '—'),
  //         '{{DEVICES}}' => $deviceDisplay,
  //         '{{REQUESTER}}' => h($sea['requester'] ?? '—'),
  //         '{{DESCRIPTION}}' => nl2br_h($sea['description'] ?? '—'),
  //         '{{JUSTIFICATION}}' => nl2br_h($sea['justification'] ?? '—'),
  //         '{{IMPACT}}' => nl2br_h($sea['impact'] ?? '—'),
  //         '{{PRIORITY}}' => h($sea['priority'] ?? '—'),
  //         '{{TARGET_DATE}}' => h($sea['target_date'] ?? '—'),
  //         '{{PARTS_TABLE}}' => $partsHtml,
  //         '{{INSTRUCTIONS_TABLE}}' => $instHtml,
  //         '{{ATTACHMENTS}}' => $attachHtml,
  //     ];

  //     $html = str_replace(array_keys($replacements), array_values($replacements), $html);

  //     $mpdf = new Mpdf(['format' => 'A4', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 20, 'margin_bottom' => 20]);
  //     $mpdf->WriteHTML($html);
  //     $pdfContent = $mpdf->Output('', 'S');
  // } catch (Exception $e) {
  //     $pdfError = ' (PDF generation failed: ' . $e->getMessage() . ')';
  //     error_log("PDF Error: " . $e->getMessage());
  // }

  ob_end_clean();

  $response = [
    'success' => true,
    'message' => ($action === 'create' ? 'SEA created' : 'SEA updated') . ' successfully!' . $pdfError,
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
