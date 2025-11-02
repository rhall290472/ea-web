<?php
// src/submit.php
require_once __DIR__ . '/../../config.php';

// Start output buffering
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../index.html');
  exit;
}

// === GET ACTION & ID ===
$action = $_POST['action'] ?? 'create';
$sea_id = $_POST['sea_id'] ?? '';

if ($action === 'create' && empty($sea_id)) {
  $sea_id = 'SEA-' . date('Ymd') . '-' . substr(str_shuffle('0123456789'), 0, 3);
}

$jsonFile = DATA_DIR . '/sea-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $sea_id) . '.json';
$existing = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

// === BUILD SEA DATA ===
$sea = [
  'id'             => $sea_id,
  'requester'      => $_POST['requester'] ?? $existing['requester'] ?? '',
  'description'    => $_POST['description'] ?? $existing['description'] ?? '',
  'justification'  => $_POST['justification'] ?? $existing['justification'] ?? '',
  'impact'         => $_POST['impact'] ?? $existing['impact'] ?? '',
  'priority'       => $_POST['priority'] ?? $existing['priority'] ?? 'Medium',
  'target_date'    => $_POST['target_date'] ?? $existing['target_date'] ?? '',
  'timestamp'      => date('Y-m-d H:i:s'),
  'status'         => $existing['status'] ?? 'Submitted',
  'version'        => ($existing['version'] ?? 0) + 1,
  'parts_json'     => $_POST['parts_json'] ?? $existing['parts_json'] ?? '[]',
  'instructions_json' => $_POST['instructions_json'] ?? $existing['instructions_json'] ?? '[]',
  'ea_number'  => $_POST['ea_number'] ?? $existing['ea_number'] ?? '',
  'revision'   => $_POST['revision'] ?? $existing['revision'] ?? '',
  'fleet'      => $_POST['fleet'] ?? $existing['fleet'] ?? '',
  'device' => is_array($_POST['device']) ? array_filter($_POST['device']) : []
];

// === HANDLE FILE UPLOADS ===
$uploadDir = UPLOAD_DIR . '/SEA/' . $sea_id;
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$attachments = $existing['attachments'] ?? [];
if (!empty($_FILES['attachments']['name'][0])) {
  foreach ($_FILES['attachments']['name'] as $k => $name) {
    if ($_FILES['attachments']['error'][$k] === UPLOAD_ERR_OK) {
      $ext = pathinfo($name, PATHINFO_EXTENSION);
      $safeName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
      $target = $uploadDir . '/' . $safeName;
      if (move_uploaded_file($_FILES['attachments']['tmp_name'][$k], $target)) {
        $attachments[] = $target;
      }
    }
  }
}
$sea['attachments'] = $attachments;

// === SAVE JSON ===
file_put_contents($jsonFile, json_encode($sea, JSON_PRETTY_PRINT));

// === GENERATE PDF ===
ob_clean();
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

function h($str)
{
  return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Parts Table
$parts = json_decode($sea['parts_json'], true);
$partsHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">';
$partsHtml .= '<tr style="background:#f8f9fa;"><th style="border:1px solid #ddd;padding:8px;">Part</th><th style="border:1px solid #ddd;padding:8px;">Desc</th><th style="border:1px solid #ddd;padding:8px;">Type</th><th style="border:1px solid #ddd;padding:8px;text-align:center;">Qty</th></tr>';
foreach ($parts as $p) {
  $partsHtml .= "<tr><td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['part']) . "</td><td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['desc']) . "</td><td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['type']) . "</td><td style=\"border:1px solid #ddd;padding:8px;text-align:center;\">" . h($p['qty']) . "</td></tr>";
}
$partsHtml .= '</table>';

// Instructions Table
$inst = json_decode($sea['instructions_json'], true);
$instHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">';
$instHtml .= '<tr style="background:#f8f9fa;"><th style="border:1px solid #ddd;padding:8px;width:8%;">#</th><th style="border:1px solid #ddd;padding:8px;">Instruction</th><th style="border:1px solid #ddd;padding:8px;">Party</th><th style="border:1px solid #ddd;padding:8px;">Notes</th></tr>';
foreach ($inst as $i => $row) {
  $instHtml .= "<tr><td style=\"border:1px solid #ddd;padding:8px;text-align:center;\">" . ($i + 1) . "</td><td style=\"border:1px solid #ddd;padding:8px;\">" . nl2br(h($row['instruction'])) . "</td><td style=\"border:1px solid #ddd;padding:8px;\">" . h($row['party']) . "</td><td style=\"border:1px solid #ddd;padding:8px;\">" . h($row['notes']) . "</td></tr>";
}
$instHtml .= '</table>';

$html = '
<!DOCTYPE html>
<html><head><style>
    body { font-family: Arial; margin: 30px; font-size: 12px; }
    h1 { text-align: center; color: #2c3e50; }
    .label { font-weight: bold; width: 150px; display: inline-block; }
</style></head><body>
<h1>Simulator Engineering Authorization (SEA)</h1>
<p style="text-align:center;"><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>
<p><span class="label">SEA ID:</span> ' . h($sea['id']) . '</p>
<p><span class="label">EA#:</span> ' . h($sea['ea_number']) . '</p>
<p><span class="label">Revision:</span> ' . h($sea['revision']) . '</p>
<p><span class="label">Fleet:</span> ' . h($sea['fleet']) . '</p>
<p><span class="label">Device(s):</span> ' . h(is_array($sea['device']) ? implode(', ', $sea['device']) : ($sea['device'] ?? '')) . '</p>
<p><span class="label">Requester:</span> ' . h($sea['requester']) . '</p>
<p><span class="label">Description:</span> ' . nl2br(h($sea['description'])) . '</p>
<p><span class="label">Justification:</span> ' . nl2br(h($sea['justification'])) . '</p>
<p><span class="label">Impact:</span> ' . nl2br(h($sea['impact'])) . '</p>
<p><span class="label">Priority:</span> ' . h($sea['priority']) . '</p>
<p><span class="label">Target Date:</span> ' . h($sea['target_date']) . '</p><h2>Affected Parts</h2>
' . (empty($parts) ? '<p><em>None</em></p>' : $partsHtml) . '
<h2>Work Instructions</h2>
' . (empty($inst) ? '<p><em>None</em></p>' : $instHtml) . '
</body></html>';

$mpdf = new Mpdf(['format' => 'A4']);
$mpdf->WriteHTML($html);
$mpdf->Output('SEA-' . $sea['id'] . '.pdf', 'D');
exit;
