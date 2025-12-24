<!-- For Password generation   -->

<?php
$password = "goldendream-25"; // Example password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

echo $hashedPassword;
?>



