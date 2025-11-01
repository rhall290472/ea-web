<?php
// src/print_sea.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

// === GET ID ===
$id = $_GET['id'] ?? '';
if (empty($id)) {
    die('No SEA ID provided');
}

$jsonFile = DATA_DIR . '/sea-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $id) . '.json';
if (!file_exists($jsonFile)) {
    die('SEA not found');
}

$sea = json_decode(file_get_contents($jsonFile), true);
if (!$sea) {
    die('Invalid JSON');
}

// === BUILD PDF ===
ob_clean();

function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// Parts Table
$parts = json_decode($sea['parts_json'] ?? '[]', true);
$partsHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
    <tr style="background:#f8f9fa;">
        <th style="border:1px solid #ddd;padding:8px;">Part</th>
        <th style="border:1px solid #ddd;padding:8px;">Desc</th>
        <th style="border:1px solid #ddd;padding:8px;">Type</th>
        <th style="border:1px solid #ddd;padding:8px;text-align:center;">Qty</th>
    </tr>';
foreach ($parts as $p) {
    $partsHtml .= "<tr>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['part']) . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['desc']) . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['type']) . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\">" . h($p['qty']) . "</td>
    </tr>";
}
$partsHtml .= '</table>';

// Instructions Table
$inst = json_decode($sea['instructions_json'] ?? '[]', true);
$instHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
    <tr style="background:#f8f9fa;">
        <th style="border:1px solid #ddd;padding:8px;width:8%;">#</th>
        <th style="border:1px solid #ddd;padding:8px;">Instruction</th>
        <th style="border:1px solid #ddd;padding:8px;">Party</th>
        <th style="border:1px solid #ddd;padding:8px;">Notes</th>
    </tr>';
foreach ($inst as $i => $row) {
    $instHtml .= "<tr>
        <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\">" . ($i+1) . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . nl2br(h($row['instruction'])) . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . h($row['party']) . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . h($row['notes']) . "</td>
    </tr>";
}
$instHtml .= '</table>';

// Device(s) as comma-separated
$deviceDisplay = is_array($sea['device']) ? implode(', ', $sea['device']) : ($sea['device'] ?? '—');

$html = '
<!DOCTYPE html>
<html><head><style>
    body { font-family: Arial; margin: 30px; font-size: 12px; }
    h1 { text-align: center; color: #2c3e50; }
    .label { font-weight: bold; width: 150px; display: inline-block; }
    table { width: 100%; margin: 10px 0; }
</style></head><body>
<h1>Simulator Engineering Authorization (SEA)</h1>
<p style="text-align:center;"><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>

<p><span class="label">SEA ID:</span> ' . h($sea['id']) . '</p>
<p><span class="label">EA#:</span> ' . h($sea['ea_number'] ?? '—') . '</p>
<p><span class="label">Revision:</span> ' . h($sea['revision'] ?? '—') . '</p>
<p><span class="label">Fleet:</span> ' . h($sea['fleet'] ?? '—') . '</p>
<p><span class="label">Device(s):</span> ' . h($deviceDisplay) . '</p>
<p><span class="label">Requester:</span> ' . h($sea['requester']) . '</p>
<p><span class="label">Description:</span> ' . nl2br(h($sea['description'])) . '</p>
<p><span class="label">Justification:</span> ' . nl2br(h($sea['justification'])) . '</p>
<p><span class="label">Impact:</span> ' . nl2br(h($sea['impact'])) . '</p>
<p><span class="label">Priority:</span> ' . h($sea['priority']) . '</p>
<p><span class="label">Target Date:</span> ' . h($sea['target_date']) . '</p>

<h2>Affected Parts</h2>
' . (empty($parts) ? '<p><em>None</em></p>' : $partsHtml) . '

<h2>Work Instructions instructions</h2>
' . (empty($inst) ? '<p><em>None</em></p>' : $instHtml) . '

</body></html>';

// === CREATE mPDF & OUTPUT ===
$mpdf = new Mpdf(['format' => 'A4']);
$mpdf->WriteHTML($html);
$mpdf->Output('SEA-' . $sea['id'] . '.pdf', 'D');
exit;