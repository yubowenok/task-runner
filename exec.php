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

function runTask($task, $source) {
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

  exec("$sql_admin < $db_init_file 2> $error_file");
  exec("$sql_admin $hash < $init_file 2> $error_file");

  exec("$sql_jail $hash < $source_file > $output_file 2> $error_file");
  exec("diff -q --strip-trailing-cr $output_file $answer_file > $diff_file");

  $output = file_get_contents($output_file);
  $error = file_get_contents($error_file);
  // Suppress warning on using password from CLI.
  $error = preg_replace("/[mysql: ]*\\[Warning\\]* Using a password on the command line interface can be insecure./i", '', $error);
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

  // Do not remove $sandboxDir if history is wanted.
  //exec("rm -r $sandboxDir");

  $result = array('error' => $error, 'diff' => $diff, 'output' => $output);
  return $result;
}

function getTasks() {
  $result = array();

  if ($handle = opendir('tasks/')) {
    while (false !== ($entry = readdir($handle))) {
      if ($entry == "." || $entry == ".." || $entry == "all-tasks.html") continue;

      $taskDir = 'tasks/' . $entry . '/';

      $info = file_get_contents($taskDir . $entry . '.info');
      $info = explode("\n", $info);

      $description = file_get_contents($taskDir . $entry . '.html');
      $answer = file_get_contents($taskDir . $entry . '.ans');

      $tables = array();
      $tableDir = $taskDir . '/tables/';
      if ($tableHandle = opendir($tableDir)) {
        while (false !== ($tableEntry = readdir($tableHandle))) {
          if ($tableEntry == "." || $tableEntry == "..") continue;
          $tableContent = file_get_contents($tableDir . '/' . $tableEntry);
          array_push($tables, array(
            'name' => $tableEntry,
            'content' => $tableContent
          ));
        }
      }

      array_push($result, array(
        'id' => $info[0],
        'title' => $info[1],
        'tables' => $tables,
        'description' => $description,
        'answer' => $answer
      ));
    }
  }

  $all_tasks_description = file_get_contents('tasks/all-tasks.html');
  array_push($result, array(
    'id' => 'all',
    'title' => 'All Tasks',
    'description' => $all_tasks_description
  ));
  return $result;
}
?>
