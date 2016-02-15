<?php
include 'exec.php';

if (!isset($_FILES['file']) || is_array($_FILES['file']['error'])) {
  abort('upload failed, missing zip file');
}

$file = $_FILES['file'];

if ($file['size'] > 100000) {
  abort('file size cannot exceed 100KB');
}

$hash = randomString();
while (file_exists("sandbox/file_$hash/")) {
  $hash = randomString();
}
$sandboxDir = "sandbox/file_$hash/";
mkdir($sandboxDir);

$zip = $sandboxDir . 'sources.zip';
move_uploaded_file($file['tmp_name'], $zip);

exec("unzip $zip -d $sandboxDir");

set_time_limit(120);

$tasks = getTasks();
$result = array();
foreach ($tasks as $task) {
  if ($task['id'] == 'all') continue;
  $source_file = $sandboxDir . $task['id'] . '.sql';
  if (!file_exists($source_file)) {
    array_push($result, array(
      'id' => $task['id'],
      'error' => 'no solution found for the task, expecting file name "' . $task['id'] . '.sql"',
      'diff' => '',
      'output' => ''
    ));
  } else {
    $source = file_get_contents($source_file);
    $runResult = runTask($task['id'], $source);
    $runResult['id'] = $task['id'];
    array_push($result, $runResult);
  }
}

header("Content-type: application/json");
echo json_encode($result);

// Do not remove $sandboxDir if history is wanted.
//exec("rm -r $sandboxDir");
?>
