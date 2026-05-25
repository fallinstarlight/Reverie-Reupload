<?php

/* Logout script, destroys the session and redirects to the login page */
require_once '../config/auth.php';
auth::destroy();
header('Location: ../../login.php');
exit();
?>