<?php
include 'exec.php';

header("Content-type: application/json");

$error = "Warning: Using a password on the command line interface can be insecure.";
$error2 = "mysql: [Warning] Using a password on the command line interface can be insecure.";
$error = preg_replace("/(mysql: )*(\\[Warning\\]|Warning:)* Using a password on the command line interface can be insecure./i", '', $error2);


echo json_encode($error);

?>
