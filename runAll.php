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
  $task_id = $task['id'];
  $map_py = "$sandboxDir/$task_id/map.py";
  $red_py = "$sandboxDir/$task_id/reduce.py"; 
  if (!file_exists($map_py) || !file_exists($red_py)) {
    array_push($result, array(
      'id' => $task['id'],
      'error' => "Cannot find mapper or reducer.\nExpecting $task_id/map.py and $task_id/reduce.py",
      'diff' => '',
      'output' => ''
    ));
  } else {
    $map_source = file_get_contents($map_py);
    $red_source = file_get_contents($red_py);
    $runResult = runTask($task['id'], $map_source, $red_source);
    $runResult['id'] = $task['id'];
    array_push($result, $runResult);
  }
}

header("Content-type: application/json");
echo json_encode($result);

// Do not remove $sandboxDir if history is wanted.
//exec("rm -r $sandboxDir");
?>
