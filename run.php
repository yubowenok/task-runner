<?php

function randomString($length = 8) {
  $chars= 'abcdefghijklmnopqrstuvwxyz0123456789';
  $len = strlen($chars);
  $str = '';
  for ($i = 0; $i < $length; $i++) {
    $r = rand(0, $len - 1);
    if ($i == 0 && '0' <= $chars[$r] && $chars[$r] <= '9') {
      $i--;
      continue;
    }
    $str .= $chars[$r];
  }
  return $str;
}

$_POST = array_merge($_POST, json_decode(file_get_contents('php://input'), true));

$task = $_POST['task'];
$source = $_POST['source'];
$hash = 'run_' . randomString();

$sandboxDir = "sandbox/$hash/";
mkdir($sandboxDir);

$taskDir = "tasks/$task/";
$source_file = $sandboxDir . 'user.sql';
file_put_contents($source_file, $source);
$output_file = $sandboxDir . 'output';
$error_file = $sandboxDir . 'error';
$diff_file = $sandboxDir . 'diff';
$db_init_file = $sandboxDir . 'db_init.sql';
$db_destroy_file = $sandboxDir . 'db_destroy.sql';
$answer_file = $taskDir . $task . '.ans';
$init_file = $taskDir . $task . '.sql';

$sql_admin = "mysql --user=task_runner --password=task_runner --local-infile=1";
$sql_jail = "mysql --user=$hash --password=$hash";

file_put_contents($db_init_file,
  join(";\n", array(
    "create database $hash",
    "create user '$hash'@'localhost' identified by '$hash'",
    "grant all privileges on $hash.* to '$hash'@'localhost'",
    "flush privileges"
  )) . ';'
);

exec("$sql_admin < $db_init_file");
exec("$sql_admin $hash < $init_file");

exec("$sql_jail $hash < $source_file > $output_file 2> $error_file");
exec("diff -q --strip-trailing-cr $output_file $answer_file > $diff_file");

$output = file_get_contents($output_file);
$error = file_get_contents($error_file);
$diff = file_get_contents($diff_file);

if (!empty($diff)) {
  exec("diff -y --strip-trailing-cr $output_file $answer_file > $diff_file");
  $diff = file_get_contents($diff_file);
}

file_put_contents($db_destroy_file,
  join(";\n", array(
    "revoke all privileges on $hash.* from '$hash'@'localhost'",
    "drop user '$hash'@'localhost'",
    "drop database $hash"
  )) . ';'
);

exec("$sql_admin < $db_destroy_file");
exec("rm -r $sandboxDir");

$result = array('error' => $error, 'diff' => $diff, 'output' => $output);
header("Content-type: application/json");
echo json_encode($result);
?>
