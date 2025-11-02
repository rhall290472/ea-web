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
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function nl2br_h($s) { return nl2br(h($s)); }

// ------------------------------------------------------------------
// 3. PARTS TABLE
// ------------------------------------------------------------------
$parts = json_decode($sea['parts_json'] ?? '[]', true) ?? [];
$partsHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
    <tr style="background:#f8f9fa;">
        <th style="border:1px solid #ddd;padding:8px;">Part</th>
        <th style="border:1px solid #ddd;padding:8px;">Desc</th>
        <th style="border:1px solid #ddd;padding:8px;">Type</th>
        <th style="border:1px solid #ddd;padding:8px;text-align:center;">Qty</th>
    </tr>';
foreach ($parts as $p) {
    $partsHtml .= "<tr>
        <td style=\"border:1px solid #ddd;padding:8px;\">".h($p['part']??'')."</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">".h($p['desc']??'')."</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">".h($p['type']??'')."</td>
        <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\">".h($p['qty']??'')."</td>
    </tr>";
}
$partsHtml .= '</table>';
if (empty($parts)) $partsHtml = '<p><em>None</em></p>';

// ------------------------------------------------------------------
// 4. INSTRUCTIONS TABLE  (no “party” column)
// ------------------------------------------------------------------
$inst = json_decode($sea['instructions_json'] ?? '[]', true) ?? [];
$instHtml = '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:11px;">
    <tr style="background:#f8f9fa;">
        <th style="border:1px solid #ddd;padding:8px;width:8%;">#</th>
        <th style="border:1px solid #ddd;padding:8px;">Instruction</th>
        <th style="border:1px solid #ddd;padding:8px;">Notes</th>
    </tr>';
foreach ($inst as $i => $row) {
    $instHtml .= "<tr>
        <td style=\"border:1px solid #ddd;padding:8px;text-align:center;\">".($i+1)."</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">".nl2br_h($row['instruction']??'')."</td>
        <td style=\"border:1px solid #ddd;padding:8px;\">".h($row['notes']??'')."</td>
    </tr>";
}
$instHtml .= '</table>';
if (empty($inst)) $instHtml = '<p><em>None</em></p>';

// ------------------------------------------------------------------
// 5. ATTACHMENTS (list of clickable links)
// ------------------------------------------------------------------
$attachHtml = '';
if (!empty($sea['attachments'])) {
    $attachHtml = '<h2>Attachments</h2><ul style="font-size:11px;">';
    foreach ($sea['attachments'] as $url) {
        $name = basename($url);
        $full = (strpos($url,'http')===0) ? $url : 'http://'.$_SERVER['HTTP_HOST'].$url;
        $attachHtml .= "<li><a href=\"{$full}\">{$name}</a></li>";
    }
    $attachHtml .= '</ul>';
}

// ------------------------------------------------------------------
// 6. DEVICE DISPLAY
// ------------------------------------------------------------------
$deviceDisplay = is_array($sea['device'] ?? []) ? implode(', ', $sea['device']) : ($sea['device'] ?? '—');

// ------------------------------------------------------------------
// 7. FINAL HTML
// ------------------------------------------------------------------
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<style>
    body {font-family:Arial;margin:30px;font-size:12px;}
    h1   {text-align:center;color:#2c3e50;}
    .label {font-weight:bold;width:150px;display:inline-block;}
    a    {color:#0066cc;text-decoration:underline;}
</style>
</head>
<body>
<h1>Simulator Engineering Authorization (SEA)</h1>
<p style="text-align:center;"><strong>Generated:</strong> <?=date('Y-m-d H:i:s')?></p>

<p><span class="label">SEA ID:</span>      <?=h($sea['id'])?></p>
<p><span class="label">EA#:</span>          <?=h($sea['ea_number']??'—')?></p>
<p><span class="label">Revision:</span>    <?=h($sea['revision']??'—')?></p>
<p><span class="label">Fleet:</span>       <?=h($sea['fleet']??'—')?></p>
<p><span class="label">Device(s):</span>  <?=h($deviceDisplay)?></p>
<p><span class="label">Requester:</span>   <?=h($sea['requester']??'—')?></p>

<p><span class="label">Description:</span>   <?=nl2br_h($sea['description']??'—')?></p>
<p><span class="label">Justification:</span><?=nl2br_h($sea['justification']??'—')?></p>
<p><span class="label">Impact:</span>       <?=nl2br_h($sea['impact']??'—')?></p>
<p><span class="label">Priority:</span>     <?=h($sea['priority']??'—')?></p>
<p><span class="label">Target Date:</span>  <?=h($sea['target_date']??'—')?></p>

<h2>Affected Parts</h2>
{$partsHtml}

<h2>Work Instructions</h2>
{$instHtml}

{$attachHtml}
</body>
</html>
HTML;

// ------------------------------------------------------------------
// 8. CREATE & SEND PDF
// ------------------------------------------------------------------
ob_clean();
$mpdf = new Mpdf(['format'=>'A4']);
$mpdf->WriteHTML($html);

// OPTIONAL: embed any attached PDFs that live on the server
if (!empty($sea['attachments'])) {
    foreach ($sea['attachments'] as $url) {
        $file = $_SERVER['DOCUMENT_ROOT'] . parse_url($url, PHP_URL_PATH);
        if (is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf') {
            $mpdf->AddPage();
            $mpdf->SetImportUse();
            $pageCount = $mpdf->SetSourceFile($file);
            for ($i=1; $i<=$pageCount; $i++) {
                $tpl = $mpdf->ImportPage($i);
                $mpdf->UseTemplate($tpl);
                if ($i < $pageCount) $mpdf->AddPage();
            }
        }
    }
}

$mpdf->Output('SEA-' . $sea['id'] . '.pdf', 'D');
exit;