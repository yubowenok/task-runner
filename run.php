<?php

$_POST = array_merge($_POST, json_decode(file_get_contents('php://input'), true));

$task = $_POST['task'];
$source = $_POST['source'];

$source_file = 'sandbox/user.sql';
$output_file = 'sandbox/output';
$error_file = 'sandbox/error';
$diff_file = 'sandbox/diff';

$answer_file = 'tasks/' . $task . '/' . $task . '.ans';
$init_file = 'tasks/' . $task . '/' . $task . '.sql';

file_put_contents($source_file, $source);

exec('mysql --user=bowen --password=bowen test < ' . $init_file);
exec('mysql --user=bowen --password=bowen test < ' . $source_file . ' > ' . $output_file . ' 2> ' . $error_file);
exec('diff -q --strip-trailing-cr ' . $output_file . ' ' . $answer_file . ' > ' . $diff_file);

$output = file_get_contents($output_file);
$error = file_get_contents($error_file);
$diff = file_get_contents($diff_file);

if (!empty($diff)) {
  exec('diff -y --strip-trailing-cr ' . $output_file . ' ' . $answer_file . ' > ' . $diff_file);
  $diff = file_get_contents($diff_file);
}

$result = array('error' => $error, 'diff' => $diff, 'output' => $output);
header("Content-type: application/json");
echo json_encode($result);

exec('rm sandbox/*');
?>
