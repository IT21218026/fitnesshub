<?php
require_once 'includes/config.php';

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?> 