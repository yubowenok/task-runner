<?php
include 'exec.php';
header("Content-type: application/json");
echo json_encode(getTasks());
?>
