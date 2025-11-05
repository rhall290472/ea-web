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
// 3. PARTS TABLE
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
        <td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['part'] ?? '') . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['desc'] ?? '') . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . h($p['type'] ?? '') . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\">" . h($p['qty'] ?? '') . "</td>
    </tr>";
}
$partsHtml .= '</tbody></table>';
if (empty($parts)) {
  $partsHtml = '<p style="font-style:italic;color:#666;margin:15px 0;">None</p>';
}

// ------------------------------------------------------------------
// 4. INSTRUCTIONS TABLE
// ------------------------------------------------------------------
$inst = json_decode($sea['instructions_json'] ?? '[]', true) ?? [];
$instHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
    <thead>
        <tr style="background:#f8f9fa;">
            <th style="border:1px solid #ddd;padding:8px;width:8%;">#</th>
            <th style="border:1px solid #ddd;padding:8px;">Instruction</th>
            <th style="border:1px solid #ddd;padding:8px;">Notes</th>
        </tr>
    </thead>
    <tbody>';
foreach ($inst as $i => $row) {
  $instHtml .= "<tr>
        <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\">" . ($i + 1) . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . nl2br_h($row['instruction'] ?? '') . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . h($row['notes'] ?? '') . "</td>
    </tr>";
}
$instHtml .= '</tbody></table>';
if (empty($inst)) {
  $instHtml = '<p style="font-style:italic;color:#666;margin:15px 0;">None</p>';
}

// ------------------------------------------------------------------
// 5. ATTACHMENTS (clickable links)
// ------------------------------------------------------------------
$attachHtml = '';
if (!empty($sea['attachments']) && is_array($sea['attachments'])) {
  $attachHtml = '<h2 style="margin-top:30px;font-size:14px;">Attachments</h2>
        <ul style="font-size:11px;margin:10px 0 20px 20px;">';
  foreach ($sea['attachments'] as $url) {
    $name = basename($url);
    $fullUrl = (strpos($url, 'http') === 0) ? $url : 'http://' . $_SERVER['HTTP_HOST'] . $url;
    $attachHtml .= "<li><a href=\"{$fullUrl}\" target=\"_blank\">" . h($name) . "</a></li>";
  }
  $attachHtml .= '</ul>';
}

// ------------------------------------------------------------------
// 6. DEVICE DISPLAY (FIXED: handles array safely)
// ------------------------------------------------------------------
$deviceDisplay = '';
if (!empty($sea['device'])) {
  $devices = is_array($sea['device']) ? $sea['device'] : [$sea['device']];
  $deviceDisplay = h(implode(', ', array_filter($devices)));
} else {
  $deviceDisplay = '—';
}

// ------------------------------------------------------------------
// 7. FINAL HTML
// ------------------------------------------------------------------
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {font-family:Arial,Helvetica,sans-serif;margin:30px;font-size:12px;line-height:1.5;}
        h1 {text-align:center;color:#2c3e50;margin-bottom:5px;}
        h2 {font-size:14px;color:#2c3e50;margin:25px 0 10px 0;}
        .label {font-weight:bold;display:inline-block;width:150px;color:#444;}
        p {margin:8px 0;}
        table {width:100%;border-collapse:collapse;margin:15px 0;}
        th, td {border:1px solid #ddd;padding:8px;font-size:11px;}
        th {background:#f8f9fa;text-align:left;}
        a {color:#0066cc;text-decoration:underline;}
        a:hover {text-decoration:none;}
        .generated {text-align:center;color:#666;font-size:10px;margin-top:20px;}
    </style>
</head>
<body>
    <h1>Simulator Engineering Authorization (SEA)</h1>
    <p class="generated"><strong>Generated:</strong> {{DATE}}</p>

    <p style="margin:8px 0;">
        <span class="label">EA#:</span> {{EA_NUMBER}}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <span class="label">Revision:</span> {{REVISION}}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <span class="label">SEA ID:</span> {{SEA_ID}}
    </p>

    <p><span class="label">Description:</span><br>{{DESCRIPTION}}</p>
    <!-- <p><span class="label">SEA ID:</span> {{SEA_ID}}</p>
    <p><span class="label">EA#:</span> {{EA_NUMBER}}</p>
    <p><span class="label">Revision:</span> {{REVISION}}</p> -->
    <p><span class="label">Fleet:</span> {{FLEET}}</p>
    <p><span class="label">Device(s):</span> {{DEVICES}}</p>
    <p><span class="label">Requester:</span> {{REQUESTER}}</p>
    <p><span class="label">Status:</span> {{STATUS}}</p>  <!-- NEW: Status display -->
    
    <p><span class="label">Justification:</span><br>{{JUSTIFICATION}}</p>
    <p><span class="label">Impact:</span><br>{{IMPACT}}</p>
    <p><span class="label">Priority:</span> {{PRIORITY}}</p>
    <p><span class="label">Target Date:</span> {{TARGET_DATE}}</p>

    <h2>Affected Parts</h2>
    {{PARTS_TABLE}}

    <h2>Work Instructions</h2>
    {{INSTRUCTIONS_TABLE}}

    {{ATTACHMENTS}}
</body>
</html>
HTML;

// Replace placeholders
$replacements = [
  '{{DATE}}'           => date('Y-m-d H:i:s'),
  '{{SEA_ID}}'         => h($sea['id'] ?? ''),
  '{{EA_NUMBER}}'      => h($sea['ea_number'] ?? '—'),
  '{{REVISION}}'       => h($sea['revision'] ?? '—'),
  '{{FLEET}}'          => h($sea['fleet'] ?? '—'),
  '{{DEVICES}}'        => $deviceDisplay,
  '{{REQUESTER}}'      => h($sea['requester'] ?? '—'),
  '{{STATUS}}'         => h($sea['status'] ?? 'Planning'),  // NEW
  '{{DESCRIPTION}}'    => nl2br_h($sea['description'] ?? '—'),
  '{{JUSTIFICATION}}'  => nl2br_h($sea['justification'] ?? '—'),
  '{{IMPACT}}'          => nl2br_h($sea['impact'] ?? '—'),
  '{{PRIORITY}}'       => h($sea['priority'] ?? '—'),
  '{{TARGET_DATE}}'    => h($sea['target_date'] ?? '—'),
  '{{PARTS_TABLE}}'    => $partsHtml,
  '{{INSTRUCTIONS_TABLE}}' => $instHtml,
  '{{ATTACHMENTS}}'     => $attachHtml,
];

$html = str_replace(array_keys($replacements), array_values($replacements), $html);

// ------------------------------------------------------------------
// 8. CREATE & SEND PDF
// ------------------------------------------------------------------
ob_clean();
$mpdf = new Mpdf([
  'format' => 'A4',
  'margin_left' => 15,
  'margin_right' => 15,
  'margin_top' => 20,
  'margin_bottom' => 20,
]);

$mpdf->WriteHTML($html);

// ------------------------------------------------------------------
// 9. EMBED ATTACHED PDF FILES (if local)
// ------------------------------------------------------------------
/*
if (!empty($sea['attachments']) && is_array($sea['attachments'])) {
  foreach ($sea['attachments'] as $url) {
    // Only process local files (not external URLs)
    if (strpos($url, 'http') === 0) continue;

    $filePath = $_SERVER['DOCUMENT_ROOT'] . parse_url($url, PHP_URL_PATH);
    if (!is_file($filePath)) continue;

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') continue;

    try {
      $mpdf->AddPage();
      $mpdf->SetImportUse();
      $pageCount = $mpdf->SetSourceFile($filePath);
      for ($i = 1; $i <= $pageCount; $i++) {
        $tpl = $mpdf->ImportPage($i);
        $mpdf->UseTemplate($tpl);
        if ($i < $pageCount) {
          $mpdf->AddPage();
        }
      }
    } catch (Exception $e) {
      // Silently fail on corrupt PDF
      error_log("Failed to embed attachment: $filePath - " . $e->getMessage());
    }
  }
}
*/
// ------------------------------------------------------------------
// 10. OUTPUT
// ------------------------------------------------------------------
$filename = 'SEA-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $sea['id']) . '.pdf';
$mpdf->Output($filename, 'D');
exit;
