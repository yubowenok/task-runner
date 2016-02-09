<?php

$result = array();

if ($handle = opendir('tasks/')) {
  while (false !== ($entry = readdir($handle))) {
    if ($entry == "." || $entry == "..") continue;

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

header("Content-type: application/json");
echo json_encode($result);
?>
