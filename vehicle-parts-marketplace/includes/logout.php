<?php
session_start();
include 'includes/config.php';

// Destroy session
session_destroy();

// Redirect to homepage
header("Location: ../index.php");
exit();
?>