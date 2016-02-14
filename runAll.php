<?php
include 'exec.php';

header("Content-type: application/json");

if (!isset($_FILES['file']) || is_array($_FILES['file']['error'])) {
  abort('upload failed');
}

$file = $_FILES['file'];

if ($file['size'] > 100000) {
  abort('file size cannot exceed 100KB');
}

$hash = randomString();
$sandboxDir = "sandbox/file_$hash/";
mkdir($sandboxDir);

$zip = $sandboxDir . 'sources.zip';
move_uploaded_file($file['tmp_name'], $zip);

exec("unzip $zip -d $sandboxDir");

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
    array_push($result, runTask($task['id'], $source));
  }
}
echo json_encode($result);

// Do not remove $sandboxDir if history is wanted.
//exec("rm -r $sandboxDir");
?>
