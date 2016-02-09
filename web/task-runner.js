var taskRunner = angular.module('taskRunner', []);

/** @private @const {string} */
var CONNECT_ERROR_ = 'Cannot connect to server';
/**
 * @typedef {{
     *   id: string,
     *   title: string,
     *   tables: !Array<string>
     * }}
 */
var Task;

taskRunner.controller('testCtrl', ['$scope', '$http',
  function($scope, $http) {
    /**
     * Task specification.
     * @type {!Array<Task>}
     */
    $scope.tasks = [];

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
    $scope.connectError = '';

    /**
     * Server check result.
     * @type {{
     *   processed: boolean,
     *   output: string,
     *   diff: string,
     *   error: string
     * }}
     */
    $scope.result = {
      processed: false
    };

    $scope.$watch('selected', function() {
      $scope.currentTask = $scope.taskById[$scope.selected];
      $scope.result = {
        processed: false
      };
      if ($scope.currentTask == null) {
        return;
      }
    });

    // Fetch tasks.
    $http.post('./tasks.php')
      .success(function(data) {
        $scope.tasks = data;
        $scope.taskById = {};
        data.forEach(function(task) {
          $scope.taskById[task.id] = task;
        });
        // By default select the first task.
        $scope.selected = _.first(data).id;
      })
      .error(function() {
        $scope.connectError = CONNECT_ERROR_;
      });

    $scope.submit = function() {
      $scope.running = true;
      $scope.connectError = '';
      $http.post('./run.php',{
        task: $scope.currentTask.id,
        source: $scope.source
      }).success(function(data) {
          data.processed = true;
          $scope.result = data;
          $scope.running = false;
        })
        .error(function() {
          $scope.connectError = CONNECT_ERROR_;
          $scope.running = false;
        });
    };
  }
]);
