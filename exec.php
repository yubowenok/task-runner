<?php

function abort($msg)
{
  header("Content-type: application/json");
  http_response_code(500);
  echo json_encode($msg);
  exit();
}

function randomString($length = 8)
{
  $chars= 'abcdefghijklmnopqrstuvwxyz0123456789';
  $len = strlen($chars);
  $str = '';
  for ($i = 0; $i < $length; $i++)
  {
    $r = rand(0, $len - 1);
    if ($i == 0 && '0' <= $chars[$r] && $chars[$r] <= '9')
    {
      $i--;
      continue;
    }
    $str .= $chars[$r];
  }
  return $str;
}

function parseError($error_file, $abort_on_error)
{
  $sys_errors = file_get_contents($error_file);
  if ($sys_errors !== '' && $abort_on_error) abort($sys_errors);
	return $sys_errors;
}

function runTask($task, $map_py, $red_py)
{
  $hash = randomString();
  while (file_exists("sandbox/run_$hash/"))
    $hash = randomString();
  $hash = 'run_' . $hash;
  $sandboxDir = "sandbox/$hash/";
  mkdir($sandboxDir, 0777);
  $task_dir = "tasks/$task/";
  $map_file = $sandboxDir . 'map.py';
  $red_file = $sandboxDir . 'reduce.py';
  file_put_contents($map_file, $map_py);
  file_put_contents($red_file, $red_py);
  $sample_tables = '';
  $mapper = 2;
  $reducer = 2;

  $config = getTaskConfig();
  foreach($config as $pattern => $def)
  {
    $matched = @preg_match($pattern, $task);
    if ($matched)
    {
      if (array_key_exists('sample_tables', $def))
        $sample_tables = 'tasks/' . $def['sample_tables'];
      if (array_key_exists('mapper', $def))
        $mapper = intval($def['mapper']);
      if (array_key_exists('reducer', $def))
        $reducer = intval($def['reducer']);
    } else if ($matched === false)
      abort('invalid regex pattern in task config: ' . $pattern);
  }

  $raw_input_files = array();
  if ($handle = opendir($sample_tables))
  {
    while (false !== ($entry = readdir($handle)))
    {
      if ($entry == '.' || $entry == '..') continue;
      array_push($raw_input_files, $sample_tables . $entry);
    }
  }

  // File to store all errors.
  $error_file = $sandboxDir . 'error';

  $input_folder = $sandboxDir . 'inputs/';
  mkdir($input_folder, 0777);

  // Partition the input files.
  foreach ($raw_input_files as $file)
  {
    exec("python partition.py $input_folder $file < $file 2>> $error_file");
  }
  // Fetch the new input files.
  $input_files = array();
  if ($handle = opendir($input_folder))
  {
    while (false !== ($entry = readdir($handle)))
    {
      if ($entry == '.' || $entry == '..') continue;
      array_push($input_files, $input_folder . $entry);
    }
  }

  // Run the mapper.
  $map_output = $sandboxDir . 'map_output';
  if ($mapper == 2)
  {
    foreach ($input_files as $file)
    {
      $dash = strrpos($file, '-');
      $basename = substr($file, $dash);
      exec("export mapreduce_map_input_file=$basename; python $map_file < $file >> $map_output 2>> $error_file");
    }
  }
  else
  {
    // use the original files and export the file names
    foreach ($raw_input_files as $file)
      exec("export mapreduce_map_input_file=$file; python $map_file < $file >> $map_output 2>> $error_file");
  }

  // Sort the mapper outputs and generate reducer inputs.
  $map_output_sorted = $sandboxDir . 'map_output_sorted';
  exec("sort $map_output > $map_output_sorted");
  exec("python combine.py $sandboxDir < $map_output_sorted 2>> $error_file");
  $red_input1 = $sandboxDir . 'red_input1';
  $red_input2 = $sandboxDir . 'red_input2';
  $red_output = $sandboxDir . 'red_output';
  // Run the reducer.
  if ($reducer == 1) {
    exec("cat $red_input2 >> $red_input1");
    exec("python $red_file < $red_input1 2>> $error_file >> $red_output");
  }
  else
  {
    exec("python $red_file < $red_input1 2>> $error_file >> $red_output");
    exec("python $red_file < $red_input2 2>> $error_file >> $red_output");
  }
  // Sort the reducer output.
  $output_file = $sandboxDir . 'output';
  exec("sort $red_output > $output_file");
  // Parse error.
  $error = parseError($error_file, false);

  // Sort and diff the answer.
  $diff_file = $sandboxDir . 'diff';
  $answer_file = $task_dir . $task . '.ans';
  exec("sort $answer_file | diff -q --strip-trailing-cr $output_file - > $diff_file");
  $output = file_get_contents($output_file);
  $diff = file_get_contents($diff_file);
  if (!empty($diff))
  {
    exec("sort $answer_file | diff -y --strip-trailing-cr $output_file - > $diff_file");
    $diff = file_get_contents($diff_file);
  }

  // Make files written from web editable.
  exec("chmod -R 0777 $sandboxDir");

  // Do not remove $sandboxDir if history is wanted.
  //exec("rm -r $sandboxDir");

  $result = array('error' => $error, 'diff' => $diff, 'output' => $output);
  return $result;
}

function getTasks()
{
  $result = array();
  $config = getTaskConfig();

  if ($handle = opendir('tasks/'))
  {
    while (false !== ($entry = readdir($handle)))
    {
      if ($entry == '.' || $entry == '..' || $entry == 'data' || !is_dir('tasks/' . $entry)) continue;

      $task = $entry;
      $task_dir = 'tasks/' . $task . '/';
      $tables_dir = $task_dir . 'tables/';

      foreach($config as $pattern => $def)
      {
        $matched = @preg_match($pattern, $task);
        if ($matched) {
          if (array_key_exists('sample_tables', $def))
            $tables_dir = 'tasks/' . $def['sample_tables'];
        } else if ($matched === false)
          abort('invalid regex pattern in task config: ' . $pattern);
      }

      $info = file_get_contents($task_dir . $task . '.info');
      $info = explode("\n", $info);

      $description = file_get_contents($task_dir . $task . '.html');
      $answer = file_get_contents($task_dir . $task . '.ans');

      $tables = array();
      if ($tableHandle = opendir($tables_dir))
      {
        while (false !== ($tableEntry = readdir($tableHandle)))
        {
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

  function cmp($a, $b)
  {
    if ($a['id'] == $b['id']) return 0;
    return ($a['id'] < $b['id']) ? -1 : 1;
  }
  usort($result, 'cmp');
  return $result;
}

function getTaskConfig()
{
  $result = array();
  if (file_exists('tasks/config'))
  {
    $config_str = file_get_contents('tasks/config');
    $lines = preg_split("/[\r\n]+/", $config_str);
    $current_task = '';

    foreach ($lines as $line)
    {
      $tokens = preg_split("/\s+/", $line);

      if (sizeof($tokens) < 2)
        continue;

      $key = $tokens[0];
      $value = $tokens[1];
      if ($key == '[task]')
      {
        $current_task = $value;
        $result[$value] = array();
      }
      else if ($key == 'sample_tables:')
        $result[$current_task]['sample_tables'] = $value;
      else if ($key == 'reducer:')
        $result[$current_task]['reducer'] = $value;
      else if ($key == 'mapper:')
        $result[$current_task]['mapper'] = $value;
    }
  }
  return $result;
}

?>
