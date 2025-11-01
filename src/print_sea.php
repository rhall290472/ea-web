<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Mpdf\Mpdf;

$id = $_GET['id'] ?? '';
if (empty($id)) die('No ID');

$jsonFile = DATA_DIR . '/sea-' . preg_replace('/[^A-Za-z0-9\-]/', '-', $id) . '.json';
if (!file_exists($jsonFile)) die('Not found');

$ecn = json_decode(file_get_contents($jsonFile), true);

// === PASTE SAME PDF CODE FROM submit.php ABOVE ===
ob_clean();
// ... [same $partsHtml, $instrHtml, $html, $mpdf code] ...
$mpdf->Output('SEA-' . $ecn['id'] . '.pdf', 'D');