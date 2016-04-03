<?php
include 'exec.php';

$_POST = array_merge($_POST, json_decode(file_get_contents('php://input'), true));

$task = $_POST['task'];
$map_source = $_POST['mapSource'];
$red_source = $_POST['redSource'];

set_time_limit(5);

header("Content-type: application/json");
echo json_encode(runTask($task, $map_source, $red_source));
?>
