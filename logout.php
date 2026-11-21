<?php
/**
 * logout.php
 * Ends the admin session and returns to the login page.
 */
require_once 'includes/functions.php';

$_SESSION = [];
session_destroy();

redirect('login.php');
