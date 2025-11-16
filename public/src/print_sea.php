<?php
// src/print_sea.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

// ------------------------------------------------------------------
// ENSURE LOG DIRECTORY EXISTS
// ------------------------------------------------------------------
$logDir = __DIR__ . '/../../storage/logs';
$logFile = $logDir . '/mpdf.log';

if (!is_dir($logDir)) {
  mkdir($logDir, 0755, true); // Creates storage/logs if missing
}
ini_set('error_log', $logFile);
error_log("PDF generation started for SEA ID: " . ($_GET['id'] ?? 'unknown'));

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

// ADD THIS HERE — BEFORE ANY DEBUG
$projectRoot = realpath(__DIR__ . '/../../');
$uploadBase = $projectRoot . '/public/data/uploads/SEA';  // CORRECT PATH
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
        <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\"> " . ($i + 1) . "</td>
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
<p>SEA ID: ' . h($sea['id']) . '| EA Number: ' . h($sea['ea_number']) . ' | EA Revision: ' . h($sea['revision']) . '</p>
<p>Priority: ' . h($sea['priority']) . ' | Target Date: ' . h($sea['target_date']) . ' | Status: ' . h($sea['status']) . '</p>
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
// 7. FIX IMAGES + EMBED ATTACHMENTS (NEW)
// ------------------------------------------------------------------
$projectRoot = realpath(__DIR__ . '/../../');
$uploadDir = $projectRoot . '/public/data/uploads/SEA/' . $sea['id'];

// Unescape JSON slashes
$html = str_replace(['\\/', '\/'], '/', $html);

// Fix local image paths in rich text (TinyMCE)
$html = preg_replace_callback(
  '#src=["\'](data/uploads/SEA/[^"\']+)["\']#i',
  function ($m) use ($projectRoot) {
    $relative = $m[1];
    $fullPath = $projectRoot . '/public/' . $relative;
    return file_exists($fullPath)
      ? 'src="file:///' . str_replace('\\', '/', $fullPath) . '"'
      : $m[0];
  },
  $html
);

// ------------------------------------------------------------------
// 8. mPDF SETUP + EMBED ATTACHMENTS
// ------------------------------------------------------------------
$mpdf = new Mpdf([
  'mode' => 'utf-8',
  'format' => 'Letter',
  'margin_left' => 15,
  'margin_right' => 15,
  'margin_top' => 30,
  'margin_bottom' => 30,
  'margin_header' => 10,
  'margin_footer' => 10,
]);

// Header & Footer
$mpdf->SetHTMLHeader('
<div style="text-align: left; border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 10px;">
    <img src="' . $projectRoot . '/public/images/United-Airlines-Logo.png" width="100" alt="Logo">
</div>');

$mpdf->SetHTMLFooter('
<div style="text-align: right; font-size: 9px; color: #666; border-top: 1px solid #ddd; padding-top: 5px;">
    Page {PAGENO} of {nbpg} | Version: ' . h($sea['version'] ?? '1') . '
</div>');

// Write main SEA content
$mpdf->WriteHTML($html);

// ------------------------------------------------------------------
// 9. EMBED ATTACHMENTS — NO HEADER OR LOGO ON THESE PAGES
// ------------------------------------------------------------------
$attachments = $sea['attachments'] ?? [];

if (!empty($attachments)) {
  // Optional: Keep header on "Attached Documents" index page
  $mpdf->AddPage();
  $mpdf->WriteHTML('<h2 style="text-align:center; color:#0d6efd; margin:30px 0 15px;">Attached Documents</h2><hr style="border:0; border-top:1px solid #ddd; margin:15px 0;">');
}

// === TURN OFF HEADER FOR ALL ATTACHMENT PAGES ===
$mpdf->SetHTMLHeader('');  // Clear header
$mpdf->defaultheaderfontstyle = '';
$mpdf->defaultheaderfontsize = 0;
$mpdf->defaultfooterfontstyle = '';
$mpdf->defaultfooterfontsize = 0;

foreach ($attachments as $url) {
  $relPath = preg_replace('#^/ea-web/public/#', '', $url);
  $filePath = realpath($projectRoot . '/public/' . $relPath);

  error_log("Attachment URL: $url");
  error_log("Resolved Path: " . ($filePath ?: 'NOT FOUND'));

  if (!$filePath || !file_exists($filePath)) {
    $mpdf->AddPage();
    $mpdf->WriteHTML('<p style="color:red; text-align:center; font-weight:bold;">[Missing: ' . h(basename($relPath)) . ']</p>');
    continue;
  }

  $filename = basename($relPath);
  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

  // Add page with NO header space and NO header content
  $mpdf->AddPage('', '', '', '', '', 15, 15, 20, 20, 0, 10); // margin_top reduced to 20
  $mpdf->WriteHTML('<h3 style="text-align:center; margin:15px 0 10px; color:#333;">Attachment: ' . h($filename) . '</h3>');

  if ($ext === 'pdf') {
    try {
      // ------------------------------------------------------------------
      // 1. Open the PDF once – we only need the page count
      // ------------------------------------------------------------------
      $pageCount = $mpdf->setSourceFile($filePath);
      error_log("PDF '$filename' opened – $pageCount page(s)");

      // ------------------------------------------------------------------
      // 2. Loop through every page
      // ------------------------------------------------------------------
      for ($i = 1; $i <= $pageCount; $i++) {

        // ---- Create a NEW page with the exact size of this source page ----
        $tplId = $mpdf->importPage($i);               // import *after* page exists
        $size  = $mpdf->getTemplateSize($tplId);      // array('width'=>…, 'height'=>…)

        $w = $size['width'];
        $h = $size['height'];

        // ---- Optional: cap gigantic pages (remove if you want 100% size) ----
        $maxW = 800;   // ~A3 landscape
        $maxH = 1100;
        $scale = 1;
        if ($w > $maxW || $h > $maxH) {
          $scale = min($maxW / $w, $maxH / $h, 1);
          $w    *= $scale;
          $h    *= $scale;
        }

        // ---- Add page with custom size (no header/footer space) ----
        $mpdf->AddPage(
          '',                // orientation (empty = custom)
          [$w, $h],          // custom format in points
          '',
          '',
          '',
          15,
          15,            // left / right margin
          25,
          25,            // top / bottom margin (room for title)
          0,
          10              // header / footer margin
        );

        // ---- Title for this page ----
        $mpdf->WriteHTML(
          '<h3 style="text-align:center;margin:8px 0 6px;color:#333;font-size:11pt;">' .
            'Attachment: ' . h($filename) . ' – Page ' . $i .
            '</h3><hr style="border:0;border-top:1px dashed #ccc;margin:6px 0 10px;">'
        );

        // ---- Embed the page (scaled if needed) ----
        if ($scale < 1) {
          $mpdf->useTemplate($tplId, 0, 0, $w, $h);   // scaled
        } else {
          $mpdf->useTemplate($tplId);                // full size
        }
      }
    } catch (Exception $e) {
      // ------------------------------------------------------------------
      // 3. Graceful fallback – show a red warning but continue
      // ------------------------------------------------------------------
      error_log("PDF embed failed for '$filename': " . $e->getMessage());
      $mpdf->AddPage('', 'Letter', '', '', '', 15, 15, 25, 25, 0, 10);
      $mpdf->WriteHTML(
        '<div style="text-align:center; padding:20px; border:2px dashed #ff6b6b; background:#fff5f5; margin:20px;">' .
          '<h3 style="color:#d32f2f;">PDF Import Failed</h3>' .
          '<p>Compression not supported. <a href="' . h($url) . '" style="color:#0d6efd; font-weight:bold;">Download ' . h($filename) . '</a></p>' .
          '<p style="font-size:9pt; color:#666;">(Error: ' . h(substr($e->getMessage(), 0, 100)) . ')</p>' .
          '</div>'
      );
    }
  }
}

// === OPTIONAL: Restore header if you want it back after attachments ===
// (Not needed since this is the end of the document)
// $mpdf->SetHTMLHeader($originalHeaderHTML);



// ------------------------------------------------------------------
// OUTPUT
// ------------------------------------------------------------------
$filename = 'SEA-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $sea['id']) . '.pdf';
$mpdf->Output($filename, 'D');
exit;
