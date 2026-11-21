<?php
/**
 * db_connect.php
 * ------------------------------------------------------------
 * Creates the MySQL connection ($conn) used by every page.
 *
 * It detects automatically whether the site is running on your
 * computer (XAMPP) or on InfinityFree, and uses the right
 * credentials for each.
 *
 * >>> AFTER creating your database on InfinityFree, replace the
 *     values in the "LIVE" block with the ones shown in your
 *     InfinityFree Control Panel -> MySQL Databases. <<<
 * ------------------------------------------------------------
 */

// Make mysqli throw exceptions on errors (so we can catch them)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Philippine time for all date() calls
date_default_timezone_set('Asia/Manila');

// Are we running on localhost (XAMPP)?
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
$isLocal    = in_array($serverName, ['localhost', '127.0.0.1', '::1'], true);

if ($isLocal) {
    // ---------- LOCAL (XAMPP) ----------
    $db_host = '127.0.0.1';
    $db_user = 'root';
    $db_pass = '';                      
    $db_name = 'equipment_borrowing';
    $db_port = 3307;                     

    // ---------- LIVE (InfinityFree) ----------
    $db_host = 'sql202.infinityfree.com';
    $db_user = 'if0_43460144';
    $db_pass = '2VgE83Irsy';
    $db_name = 'if0_43460144_equipment';
    $db_port = 3306;
}                    

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+08:00'");
} catch (mysqli_sql_exception $e) {
    // Do not show the real error to visitors (it may reveal credentials)
    http_response_code(500);
    die('<h3 style="font-family:sans-serif;color:#b00">Database connection failed.</h3>'
      . '<p style="font-family:sans-serif">Please check the credentials in <code>db_connect.php</code>.</p>');
}
