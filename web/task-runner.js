var taskRunner = angular.module('taskRunner', []);

/** @private @const {number} */
var FILE_SIZE_LIMIT_ = 100000;
/** @private @const {string} */
var FILE_SIZE_ERROR_ = 'file size cannot exceed 100KB';
/** @private @const {string} */
var CONNECT_ERROR_ = 'cannot connect to server';
/** @private @const {string} */
var UPLOAD_ERROR_ = 'cannot upload file';

/**
 * @typedef {{
     *   id: string,
     *   title: string,
     *   tables: !Array<string>
     * }}
 */
var Task;

/**
 * @typedef {{
 *   output: string,
 *   diff: string,
 *   error: string,
 *   correct: (boolean|undefined)
 * }}
 */
var TaskResult;

taskRunner.controller('testCtrl', ['$scope', '$http', '$sce',
  function($scope, $http, $sce) {
    /**
     * Task specification.
     * @type {!Array<Task>}
     */
    $scope.tasks = [];

    /**
     * Flag indicating submitting to all tasks as zip file.
     * @type {boolean}
     */
    $scope.allTasks = false;

    /**
     * Task reference by id.
     * @type {!Object<Task>}
     */
    $scope.taskById = {};

    /**
     * Selected task index.
     * @type {string}
     */
    $scope.selected = '';

    /**
     * Current selected task.
     * @type {Task}
     */
    $scope.currentTask = {};

    /**
     * Selected table index.
     * @type {number}
     */
    $scope.selectedTable = -1;

    /**
     * @type {{
     *   name: string,
     *   content: string
     * }}
     */
    $scope.currentTable = {
      name: '',
      content: ''
    };

    /**
     * String of submitted source.
     * @type {string}
     */
    $scope.source = '';

    /**
     * Whether server is running the submission.
     * @type {boolean}
     */
    $scope.running = false;

    /**
     * Error message for connection problem.
     * @type {string}
     */
    $scope.error = '';

    /**
     * Selected zip file name.
     * @type {string}
     */
    $scope.fileName = '';

    /**
     * Selected zip file.
     * @type {?File}
     */
    $scope.file = null;

    /**
     * Count of correct tasks in batch testing.
     * @type {number}
     */
    $scope.correctCount = 0;

    /**
     * Flag indicating a submission has been processed.
     */
    $scope.processed = false;

    /**
     * Server check result.
     * @type {TaskResult}
     */
    $scope.result = {
      output: '',
      diff: '',
      error: ''
    };

    /**
     * Results for batch testing.
     * @type {!Array<TaskResult>}
     */
    $scope.results = [];

    /**
     * Checks if the result is correct and writes the correct flag.
     * @param {TaskResult} result
     * @return {TaskResult}
     */
    var checkCorrect = function(result) {
      if (result.diff === '' && result.error === '') {
        result.correct = true;
      } else {
        result.correct = false;
      }
      return result;
    };

    $scope.fileSelect = function() {
      $('#file-select').trigger('click');
    };
    $('#file-select').change(function(event) {
      var fileName = event.target.files[0].name;
      $scope.$apply(function() {
        $scope.fileName = fileName;
        $scope.file = event.target.files[0];
        $scope.error = '';

        if ($scope.file.size > FILE_SIZE_LIMIT_) {
          $scope.fileName = '';
          $scope.file = null;
          $scope.error = FILE_SIZE_ERROR_;
        }
      });
    });

    $scope.$watch('selected', function() {
      if ($scope.selected == 'all') {
        $scope.allTasks = true;
      }
      $scope.allTasks = false;

      $scope.currentTask = $scope.taskById[$scope.selected];

      $scope.processed = false;
      if ($scope.currentTask == null) {
        return;
      }
      $scope.selectedTable = 0;
      $scope.currentTable = $scope.currentTask.tables != null ?
        $scope.currentTask.tables[0] : {name: '', content: ''};
    });

    /**
     * Sets the current table index.
     * @param {number} index
     */
    $scope.setTable = function(index) {
      $scope.selectedTable = index;
      $scope.currentTable = $scope.currentTask.tables[$scope.selectedTable];
    };

    // Fetch tasks.
    $http.post('./tasks.php')
      .success(function(data) {
        $scope.tasks = data;
        $scope.taskById = {};
        data.forEach(function(task) {
          $scope.taskById[task.id] = task;
          task.description = $sce.trustAsHtml(task.description);
        });
        // By default select the first task.
        $scope.selected = _.last(data).id;
      })
      .error(function() {
        $scope.error = CONNECT_ERROR_;
      });

    $scope.submit = function() {
      $scope.running = true;
      $scope.error = '';
      $http.post('./run.php',{
        task: $scope.currentTask.id,
        source: $scope.source
      }).success(function(data) {
          $scope.processed = true;
          $scope.result = checkCorrect(data);
          $scope.running = false;
        })
        .error(function(error) {
          $scope.error = error;
          $scope.running = false;
        });
    };

    $scope.showResult = function(taskIndex) {
      $('#results').children('a').removeClass('active');
      $('#results > a:nth-child(' + (taskIndex + 1) + ')').addClass('active');
      $scope.result = $scope.results[taskIndex];
      $scope.processed = true;
    };

    $scope.submitAll = function() {
      $scope.running = true;
      $scope.error = '';

      var formData = new FormData();
      formData.append('file', $scope.file);

      $http.post('./runAll.php', formData, {
        headers: {'Content-Type': undefined}
      }).success(function(data) {
          $scope.correctCount = 0;
          $scope.results = data.map(function(result) {
            var newResult = checkCorrect(result);
            $scope.correctCount += newResult.correct;
            return newResult;
          });
          $scope.processed = false;
          $scope.running = false;
        })
        .error(function(error) {
          $scope.error = error;
          $scope.running = false;
        });
    };
  }
]);
