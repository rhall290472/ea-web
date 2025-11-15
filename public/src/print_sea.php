<?php
// src/print_sea.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

// ------------------------------------------------------------------
// 1. GET THE SEA
// ------------------------------------------------------------------
$id = $_GET['id'] ?? '';
if ($id === '') {
  die('No SEA ID supplied');
}
$jsonFile = DATA_DIR . '/sea-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $id) . '.json';
if (!file_exists($jsonFile)) {
  die('SEA not found');
}
$sea = json_decode(file_get_contents($jsonFile), true);
if (!is_array($sea)) {
  die('Corrupt JSON');
}

// ------------------------------------------------------------------
// 2. HELPERS
// ------------------------------------------------------------------
function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function nl2br_h($s)
{
  return nl2br(h($s));
}

// ------------------------------------------------------------------
// 3. BUILD PARTS TABLE
// ------------------------------------------------------------------
$parts = json_decode($sea['parts_json'] ?? '[]', true) ?? [];
$partsHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
    <thead>
        <tr style="background:#f8f9fa;">
            <th style="border:1px solid #ddd;padding:8px;">Part</th>
            <th style="border:1px solid #ddd;padding:8px;">Desc</th>
            <th style="border:1px solid #ddd;padding:8px;">Type</th>
            <th style="border:1px solid #ddd;padding:8px;text-align:center;">Qty</th>
        </tr>
    </thead>
    <tbody>';
foreach ($parts as $p) {
  $partsHtml .= "<tr>
        <td style=\"border:1px solid #ddd;padding:8px;\"> " . h($p['part'] ?? '') . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\"> " . h($p['description'] ?? '') . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\"> " . h($p['type'] ?? '') . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\"> " . h($p['qty'] ?? '') . "</td>
    </tr>";
}
$partsHtml .= '</tbody></table>';
if (empty($parts)) {
  $partsHtml = '<p style="font-style:italic;color:#666;margin:15px 0;">None</p>';
}

// ------------------------------------------------------------------
// 4. BUILD INSTRUCTIONS TABLE
// ------------------------------------------------------------------
$instr = json_decode($sea['instructions_json'] ?? '[]', true) ?? [];
$instrHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
    <thead>
        <tr style="background:#f8f9fa;">
            <th style="border:1px solid #ddd;padding:8px;width:5%;">#</th>
            <th style="border:1px solid #ddd;padding:8px;width:70%;">Instruction</th>
            <th style="border:1px solid #ddd;padding:8px;width:25%;">Notes</th>
        </tr>
    </thead>
    <tbody>';
foreach ($instr as $i => $inst) {
  $instrHtml .= "<tr>
        <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\"> " . ($i+1) . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\"> " . ($inst['instruction'] ?? '') . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\"> " . nl2br_h($inst['notes'] ?? '') . "</td>
    </tr>";
}
$instrHtml .= '</tbody></table>';
if (empty($instr)) {
  $instrHtml = '<p style="font-style:italic;color:#666;margin:15px 0;">None</p>';
}

// ------------------------------------------------------------------
// 5. ATTACHMENTS LIST
// ------------------------------------------------------------------
$attach = $sea['attachments'] ?? [];
$attachHtml = '<ul style="margin:15px 0;padding-left:20px;">';
foreach ($attach as $url) {
  $name = basename($url);
  $attachHtml .= '<li><a href="' . h($url) . '">' . h($name) . '</a></li>';
}
$attachHtml .= '</ul>';
if (empty($attach)) {
  $attachHtml = '<p style="font-style:italic;color:#666;margin:15px 0;">None</p>';
}

// ------------------------------------------------------------------
// 6. MAIN HTML — RESTORED ORIGINAL FORMAT
// ------------------------------------------------------------------
$html = '<style>
    body { font-family: "Segoe UI", Tahoma, sans-serif; font-size: 12pt; color: #333; line-height: 1.4; }
    h1 { font-size: 18pt; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
    h2 { font-size: 14pt; margin-top: 30px; margin-bottom: 10px; }
    p { margin: 5px 0; font-size: 11pt; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 10pt; }
    th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
    th { background: #f8f9fa; text-align: left; }
    .italic-none { font-style: italic; color: #666; }
    img { max-width: 100%; height: auto; display: block; margin: 10px 0; border: 1px solid #eee; page-break-inside: avoid; }
</style>

<h1 style="text-align: center; margin: 20px 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">Simulator Engineering Authorization</h1>
<p>SEA ID: ' . h($sea['id']) .'| EA Number: ' . h($sea['ea_number']) . ' | EA Revision: ' . h($sea['revision']) .'</p>
<p>Priority: ' . h($sea['priority']) .' | Target Date: ' . h($sea['target_date']) .' | Status: ' . h($sea['stauts']) .'</p>
<p>Fleet: ' . h($sea['fleet']) . ' | Device: ' . h(implode(', ', (array)$sea['device'])) . '</p>
<p>Author: ' . h($sea['requester']) . '</p>
<h2>Description</h2>
<p>' . nl2br_h($sea['description']) . '</p>
<h2>Justification</h2>
<p>' . nl2br_h($sea['justification']) . '</p>
<h2>Impact</h2>
<p>' . nl2br_h($sea['impact']) . '</p>

<h2>Affected Parts</h2>
' . $partsHtml . '

<h2>Work Instructions</h2>
' . $instrHtml . '

<h2>Attachments</h2>
' . $attachHtml . '';

// ------------------------------------------------------------------
// 7. FIX IMAGES FOR PDF (CRITICAL)
// ------------------------------------------------------------------
$projectRoot = realpath(__DIR__ . '/../../');

// Unescape JSON slashes
$html = str_replace(['\\/', '\/'], '/', $html);

// Convert relative to absolute file paths
$html = preg_replace_callback(
    '#src=["\'](data/uploads/SEA/[^"\']+)["\']#i',
    function($m) use ($projectRoot) {
        $relative = $m[1];
        $fullPath = $projectRoot . '/public/' . $relative;  // Since data is in public/data
        if (file_exists($fullPath)) {
            return 'src="file:///' . str_replace('\\', '/', $fullPath) . '"';
        }
        return $m[0];
    },
    $html
);

// ------------------------------------------------------------------
// 8. mPDF SETUP
// ------------------------------------------------------------------
$mpdf = new Mpdf([
  'mode' => 'utf-8',
  'format' => 'A4',
  'margin_left' => 15,
  'margin_right' => 15,
  'margin_top' => 30,
  'margin_bottom' => 30,
  'margin_header' => 10,
  'margin_footer' => 10,
]);

$mpdf->SetHTMLHeader('
<div style="text-align: left; border-bottom: 1px solid #ddd; padding-bottom: 5px;">
    <img src="../images/United-Airlines-Logo.png" width="100" alt="Logo">
</div>');

$mpdf->SetHTMLFooter('
<div style="text-align: right; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 5px;">
    Page {PAGENO} of {nbpg} | Version: ' . h($sea['version'] ?? '1') . '
</div>');

$mpdf->WriteHTML($html);

// ------------------------------------------------------------------
// OUTPUT
// ------------------------------------------------------------------
$filename = 'SEA-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $sea['id']) . '.pdf';
$mpdf->Output($filename, 'D');
exit;