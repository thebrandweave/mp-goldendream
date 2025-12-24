<?php
session_start();
 $menuPath= "./";

require_once("middleware/auth.php");
verifyAuth();

// Redirect based on admin role
if ($_SESSION['admin_role'] === 'Verifier') {
    header("Location: ./promoter/");
} else {
    header("Location: ./dashboard/");
}
exit();
