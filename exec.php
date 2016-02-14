<?php
function abort($msg) {
  header("Content-type: application/json");
  http_response_code(500);
  echo json_encode($msg);
  exit();
}

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

  $task_dir = "tasks/$task/";
  $source_file = $sandboxDir . 'user.sql';
  file_put_contents($source_file, $source);
  $output_file = $sandboxDir . 'output';
  $error_file = $sandboxDir . 'error';
  $diff_file = $sandboxDir . 'diff';
  $db_init_file = $sandboxDir . 'db_init.sql';
  $db_destroy_file = $sandboxDir . 'db_destroy.sql';
  $answer_file = $task_dir . $task . '.ans';
  $init_file = $task_dir . $task . '.sql';

  $config = getTaskConfig();
  foreach($config as $pattern => $def) {
    $matched = @preg_match($pattern, $task);
    if ($matched) {
      if (array_key_exists('init', $def)) {
        $init_file = 'tasks/' . $def['init'];
      }
    } else if ($matched === false) {
      abort('invalid regex pattern in task config: ' . $pattern);
    }
  }

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
  $sys_errors = file_get_contents($error_file);
  if ($sys_errors !== '') abort($sys_errors);

  exec("$sql_admin $hash < $init_file 2> $error_file");
  $sys_errors = file_get_contents($error_file);
  if ($sys_errors !== '') abort($sys_errors);

  exec("$sql_jail $hash < $source_file > $output_file 2> $error_file");
  exec("diff -q --strip-trailing-cr $output_file $answer_file > $diff_file");

  $output = file_get_contents($output_file);
  $error = file_get_contents($error_file);
  // Suppress warning on using password from CLI.
  $error = preg_replace("/(mysql: )*(\\[Warning\\]|Warning:)* Using a password on the command line interface can be insecure./i", '', $error);
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

  exec("$sql_admin < $db_destroy_file 2> $error_file");
  $sys_errors = file_get_contents($error_file);
  if ($sys_errors !== '') abort($sys_errors);

  // Do not remove $sandboxDir if history is wanted.
  //exec("rm -r $sandboxDir");

  $result = array('error' => $error, 'diff' => $diff, 'output' => $output);
  return $result;
}

function getTasks() {
  $result = array();
  $config = getTaskConfig();

  if ($handle = opendir('tasks/')) {
    while (false !== ($entry = readdir($handle))) {
      if ($entry == '.' || $entry == '..' || $entry == 'data' || !is_dir('tasks/' . $entry)) continue;

      $task = $entry;
      $task_dir = 'tasks/' . $task . '/';
      $tables_dir = $task_dir . 'tables/';

      foreach($config as $pattern => $def) {
        $matched = @preg_match($pattern, $task);
        if ($matched) {
          if (array_key_exists('sample_tables', $def)) {
            $tables_dir = 'tasks/' . $def['sample_tables'];
          }
        } else if ($matched === false) {
          abort('invalid regex pattern in task config: ' . $pattern);
        }
      }

      $info = file_get_contents($task_dir . $task . '.info');
      $info = explode("\n", $info);

      $description = file_get_contents($task_dir . $task . '.html');
      $answer = file_get_contents($task_dir . $task . '.ans');

      $tables = array();
      if ($tableHandle = opendir($tables_dir)) {
        while (false !== ($tableEntry = readdir($tableHandle))) {
          if ($tableEntry == "." || $tableEntry == "..") continue;
          $tableContent = file_get_contents($tables_dir . '/' . $tableEntry);
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

function getTaskConfig() {
  $result = array();
  if (file_exists('tasks/config')) {
    $config_str = file_get_contents('tasks/config');
    $tokens = preg_split("/[\s\r\n]+/", $config_str);

    $expect_task = false;
    $expect_init = false;
    $expect_sample_tables = false;
    $current_task = '';

    foreach($tokens as $token) {
      if ($token == '[task]') {
        $expect_task = true;
      } else if ($token == 'init:') {
        $expect_init = true;
      } else if ($token == 'sample_tables:') {
        $expect_sample_tables = true;
      } else if ($expect_task) {
        $current_task = $token;
        $result[$current_task] = array();
        $expect_task = false;
      } else if ($expect_init) {
        $result[$current_task]['init'] = $token;
        $expect_init = false;
      } else if ($expect_sample_tables) {
        $result[$current_task]['sample_tables'] = $token;
        $expect_sample_tables = false;
      }
    }
  }
  return $result;
}
?>
