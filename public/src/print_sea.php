<?php
// Force show errors + log to a file in the same directory as this script
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$customLogFile = __DIR__ . '/mpdf-errors.log';  // Creates mpdf-errors.log INSIDE /src/

ini_set('log_errors', 1);
ini_set('error_log', $customLogFile);

// Quick writable test
if (!is_writable(__DIR__)) {
    die("Cannot write to script directory: " . __DIR__ . " — check permissions");
}



ob_start();  // buffer output to catch early sends
// src/print_sea.php
require_once __DIR__ . '/../../config.php';
//require_once __DIR__ . '/../vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php';

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
function nl2br_h($s) {
    $s = htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    return str_replace(["\r\n", "\r", "\n"], '<br>', $s);  // clean <br> without space
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
        <td style=\"border:1px solid #ddd;padding:8px;\">" . allow_html($row['instruction'] ?? '') . "</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">" . nl2br_h($row['notes'] ?? '') . "</td>
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

// Helper: safely allow basic HTML in rich text fields + convert newlines
function allow_html($s) {
    $s = $s ?? '—';
    $s = stripslashes($s);
    $s = str_replace(["\r\n", "\r", "\n"], '<br>', $s);
    

    // Use HTTPS if your site has SSL; otherwise keep http
    $baseUrl = 'http://simea.dentk.com/';

    // Match and replace any src="data/uploads/..." or src='data/uploads/...' (with optional spaces)
    $s = preg_replace(
        '/(src\s*=\s*["\'])\s*(data\/uploads\/[^"\']+?)\s*(["\'])/i',
        '$1' . $baseUrl . '$2$3',
        $s
    );

    // If the image is present, log a snippet of the final string (for verification)
    if (stripos($s, 'Screenshot_2026-01-21_154427.png') !== false) {
        error_log("[MPDF-DEBUG-FINAL] allow_html output snippet:\n" . substr($s, stripos($s, '<img'), 500));
    }

    return $s;
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
  '{{JUSTIFICATION}}'  => allow_html($sea['justification'] ?? '—'),
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

// Temporary bypass for PSR shim type conflict
if (class_exists('Mpdf\PsrHttpMessageShim\Request', false)) {
    class_alias('stdClass', 'Mpdf\PsrHttpMessageShim\Request');
}
if (class_exists('Mpdf\PsrHttpMessageShim\Response', false)) {
    class_alias('stdClass', 'Mpdf\PsrHttpMessageShim\Response');
}
if (class_exists('Mpdf\PsrHttpMessageShim\Stream', false)) {
    class_alias('stdClass', 'Mpdf\PsrHttpMessageShim\Stream');
}
if (class_exists('Mpdf\PsrHttpMessageShim\Uri', false)) {
    class_alias('stdClass', 'Mpdf\PsrHttpMessageShim\Uri');
}

$mpdf = new Mpdf([
  'format' => 'Letter',
  'margin_left' => 15,
  'margin_right' => 15,
  'margin_top' => 30,  // Increased to make space for header
  'margin_bottom' => 30,  // Increased to make space for footer
  'margin_header' => 10,  // Space between header and body
  'margin_footer' => 10,  // Space between footer and body
]);

$mpdf->setBasePath('http://simea.dentk.com/');

// === Enhanced debug for body images ===
$mpdf->showImageErrors = true;
$mpdf->debug = true;


// 2. Log temp dir (critical for PNG alpha/interlaced processing)
$mpdf->tempDir = sys_get_temp_dir();  // usually /tmp
error_log("mPDF tempDir: " . $mpdf->tempDir);
error_log("tempDir writable? " . (is_writable($mpdf->tempDir) ? 'YES' : 'NO - FIX THIS!'));

// 3. Inject a visible test image EARLY in body (before your main $html)
$testImageHtml = '
<p><strong>DEBUG TEST IMAGE (remote PNG - should appear if images work at all):</strong></p>
<img src="https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_272x92dp.png" width="200" alt="Google Test">
<p><strong>DEBUG TEST IMAGE (local relative - adjust if needed):</strong></p>
<img src="/images/test.png" width="100" alt="Local Test">  <!-- create a test.png if you want -->
<hr>
';

// Add this BEFORE replacing placeholders or WriteHTML
$html = $testImageHtml . $html;  // prepend to see it at top of PDF



// Set Header (with image - replace 'path/to/logo.png' with your actual image path)
$header = '
<div style="text-align: left; border-bottom: 1px solid #ddd; padding-bottom: 20px;">
    <img src="http://simea.dentk.com/images/United-Airlines-Logo.png" width="100">  <!-- Adjust width as needed -->
</div>';
$mpdf->SetHTMLHeader($header);

// Set Footer (page x of x and SEA version)
$footer = '
<div style="text-align: right; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 5px;">
    Page {PAGENO} of {nbpg} | Version: ' . h($sea['version'] ?? '1') . '
</div>';
$mpdf->SetHTMLFooter($footer);

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
ob_end_clean();
$mpdf->Output($filename, 'D');
exit;
