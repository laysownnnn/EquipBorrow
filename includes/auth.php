<?php
/**
 * includes/auth.php
 * ------------------------------------------------------------
 * Include this at the top of every page that requires login.
 * If the admin is not logged in, they are sent to login.php.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../db_connect.php';

if (empty($_SESSION['admin_id'])) {
    redirect('login.php');
}
