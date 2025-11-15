<?php
// ea-web/config.php
// Central configuration – easy to tweak without editing core files

// ------------------------------------------------------------------
// ENSURE LOG DIRECTORY EXISTS
// ------------------------------------------------------------------
$logDir = __DIR__ . '/../../storage/logs';
$logFile = $logDir . '/mpdf.log';

// ------------------------------------------------------------------
// ENSURE LOG DIRECTORY EXISTS
// ------------------------------------------------------------------
$logDir = __DIR__ . '/../../storage/logs';
$logFile = $logDir . '/mpdf.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true); // Creates storage/logs if missing
}
ini_set('error_log', $logFile);
error_reporting(E_ALL);
error_log("PDF generation started for SEA ID: " . ($_GET['id'] ?? 'unknown'));



// -----------------------------
// 1. Timezone
// -----------------------------
date_default_timezone_set('America/New_York'); // Change to your local timezone

// -----------------------------
// 2. Paths (auto-calculated)
// -----------------------------
define('ROOT_DIR', __DIR__ .'/public');                    // e.g., /var/www/ea-web
define('DATA_DIR', ROOT_DIR . '/data');         // Where SEAs are stored
define('UPLOAD_DIR', DATA_DIR . '/uploads');    // File attachments
define('PUBLIC_DIR', ROOT_DIR . '/public');     // Public web root

// Create directories if missing
foreach ([DATA_DIR, UPLOAD_DIR, DATA_DIR . '/history'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
// -----------------------------
// 3. Email Settings (optional)
// -----------------------------
define('ENABLE_EMAIL', false);                  // Set to true to send notifications
define('ADMIN_EMAIL', 'approver@yourcompany.com');
define('FROM_EMAIL', 'SEA-system@yourcompany.com');
define('SMTP_HOST', 'smtp.yourmail.com');       // Only if using SMTP
define('SMTP_PORT', 587);
define('SMTP_USER', 'user');
define('SMTP_PASS', 'pass');

// -----------------------------
// 4. Security
// -----------------------------
define('ALLOWED_EXTENSIONS', ['pdf','jpg','jpeg','png','docx','xlsx','csv','txt']);
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB

// -----------------------------
// 5. Auto-create directories
// -----------------------------
foreach ([DATA_DIR, UPLOAD_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// --- EMAIL NOTIFICATION ---
if (defined('ENABLE_EMAIL') && ENABLE_EMAIL) {
    $subject = "New SEA: {$SEA['id']}";
    $message = "A new Simulator Engineering Authorization has been submitted.\n\n";
    $message .= "ID: {$SEA['id']}\n";
    $message .= "Requester: {$SEA['requester']}\n";
    $message .= "Description: {$SEA['description']}\n";
    $message .= "View: http://yourdomain.com/src/view.php\n";

    $headers = "From: " . FROM_EMAIL;

    mail(ADMIN_EMAIL, $subject, $message, $headers);
}

// Add this line near the end
foreach ([DATA_DIR, UPLOAD_DIR, DATA_DIR . '/history'] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}
