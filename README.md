# task-runner

### System Setup
To setup, run
```bash
npm install
mkdir sandbox
chmod 777 sandbox
```

Please also make sure "unzip" is installed on the system.

### SQL Setup
A user with name/password "task_runner" needs to be created with grant option.
```sql
CREATE USER 'task_runner'@'localhost' IDENTIFIED BY 'task_runner';
GRANT ALL PRIVILEGES ON *.* TO 'task_runner'@'localhost' WITH GRANT OPTION;
```

MySQL needs to be in system path. Alternatively, you can add alias "mysql".

### Task Setup
* Each task shall be given a unique id, e.g. 'all-taxes', 'task1'.
* Create a folder under tasks/ with the id, i.e. tasks/{id}.
* An info file named with tasks/{id}/{id}.info is required.
The first line of the file contains the task id.
The second line of the file contains the task title.
* The description of the task is written to tasks/{id}/{id}.html.
HTML syntax and styles are supported.
* The sample tables for the task are written at tasks/{id}/tables/.
Each table file contains the sample table printed content.
The name of the file shall match the table name.
* tasks/{id}/{id}.sql contains the initialization script for setting up DB content before user code execution.
* Additional data can be put under tasks/{id} for initialization purpose.
Note that when the init script is called the current path is the root of task-runner.

See tasks/all-taxes and tasks/specific-taxis for task examples.

The description of all-task zip file submission (e.g. for reminding people of the correct file names) can be
written in tasks/all-tasks.html. HTML syntax and styles are supported.

It is possible that multiple tasks have the same init script.
In this case use task config file at tasks/config.
```
[task] {regex}
init: {filepath}
```
This file sets the init script to _filepath_ for tasks with ids matching _regex_.
Please note that _filepath_ is relative to tasks/.
