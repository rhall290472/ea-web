<?php
// src/submit.php
require_once __DIR__ . '/../config.php';

// Prevent output
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/index.html');
    exit;
}

$action = $_POST['action'] ?? 'create';
$sea_id = $_POST['sea_id'] ?? '';

if ($action === 'create' && empty($sea_id)) {
    $sea_id = 'SEA-' . date('Ymd') . '-' . substr(str_shuffle('0123456789'), 0, 3);
}

$jsonFile = DATA_DIR . '/sea-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $sea_id) . '.json';
$existing = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

// Build $sea
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
    'instructions_json' => $_POST['instructions_json'] ?? $existing['instructions_json'] ?? '[]'
];

// Handle uploads
$uploadDir = UPLOAD_DIR . '/SEA/' . $sea_id;
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$attachments = $existing['attachments'] ?? [];
if (!empty($_FILES['attachments']['name'][0])) {
    foreach ($_FILES['attachments']['name'] as $k => $name) {
        if ($_FILES['attachments']['error'][$k] === UPLOAD_ERR_OK) {
            $target = $uploadDir . '/' . time() . '_' . basename($name);
            move_uploaded_file($_FILES['attachments']['tmp_name'][$k], $target);
            $attachments[] = $target;
        }
    }
}
$sea['attachments'] = $attachments;

// Save JSON
file_put_contents($jsonFile, json_encode($sea, JSON_PRETTY_PRINT));

// === GENERATE PDF ===
ob_clean();
require_once __DIR__ . '/../vendor/autoload.php';
use Mpdf\Mpdf;

function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// Parts Table
$parts = json_decode($sea['parts_json'], true);
$partsHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
    <tr style="background:#f8f9fa;">
        <th style="border:1px solid #ddd;padding:8px;">Part</th>
        <th style="border:1px solid #ddd;padding:8px;">Desc</th>
        <th style="border:1px solid #ddd;padding:8px;">Type</th>
        <th style="border:1px solid #ddd;padding:8px;text-align:center;">Qty</th>
    </tr>';
foreach ($parts as $p) {
    $partsHtml .= '<tr>
        <td style="border:1px solid #ddd;padding:8px;">' . h($p['part']) . '</td>
        <td style="border:1px solid #ddd;padding:8px;">' . h($p['desc']) . '</td>
        <td style="border::1px solid #ddd;padding:8px;">' . h($p['type']) . '</td>
        <td style="border:1px solid #ddd;padding:8px;text-align:center;">' . h($p['qty']) . '</td>
    </tr>';
}
$partsHtml .= '</table>';

// Instructions Table
$inst = json_decode($sea['instructions_json'], true);
$instHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
    <tr style="background:#f8f9fa;">
        <th style="border:1px solid #ddd;padding:8px;width:8%;">#</th>
        <th style="border:1px solid #ddd;padding:8px;">Instruction</th>
        <th style="border:1px solid #ddd;padding:8px;">Party</th>
        <th style="border:1px solid #ddd;padding:8px;">Notes</th>
    </tr>';
foreach ($inst as $i => $irow) {
    $instHtml .= '<tr>
        <td style="border:1px solid #ddd;padding:8px;text-align:center;">' . ($i+1) . '</td>
        <td style="border:1px solid #ddd;padding:8px;">' . nl2br(h($irow['instruction'])) . '</td>
        <td style="border:1px solid #ddd;padding:8px;">' . h($irow['party']) . '</td>
        <td style="border:1px solid #ddd;padding:8px;">' . h($irow['notes']) . '</td>
    </tr>';
}
$instHtml .= '</table>';

$html = '
<!DOCTYPE html>
<html><head><style>
    body { font-family: Arial; margin: 30px; font-size: 12px; }
    h1 { text-align: center; color: #2c3e50; }
    table { width: 100%; margin: 10px 0; }
    td { vertical-align: top; padding: 4px 0; }
    .label { font-weight: bold; width: 150px; }
</style></head><body>
<h1>Simulator Engineering Authorization (SEA)</h1>
<p style="text-align:center;"><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>

<table>
<tr><td class="label">SEA ID:</td><td>' . h($sea['id']) . '</td></tr>
<tr><td class="label">Requester:</td><td>' . h($sea['requester']) . '</td></tr>
<tr><td class="label">Description:</td><td>' . nl2br(h($sea['description'])) . '</td></tr>
<tr><td class="label">Justification:</td><td>' . nl2br(h($sea['justification'])) . '</td></tr>
<tr><td class="label">Impact:</td><td>' . nl2br(h($sea['impact'])) . '</td></tr>
<tr><td class="label">Priority:</td><td>' . h($sea['priority']) . '</td></tr>
<tr><td class="label">Target Date:</td><td>' . h($sea['target_date']) . '</td></tr>
<tr><td class="label">Status:</td><td>' . h($sea['status']) . '</td></tr>
</table>

<h2>Affected Parts</h2>
' . (empty($parts) ? '<p><em>None</em></p>' : $partsHtml) . '

<h2>Work Instructions</h2>
' . (empty($inst) ? '<p><em>None</em></p>' : $instHtml) . '

</body></html>';

$mpdf = new Mpdf(['format' => 'A4']);
$mpdf->WriteHTML($html);
$mpdf->Output('SEA-' . $sea['id'] . '.pdf', 'D');
exit;