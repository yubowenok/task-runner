var taskRunner = angular.module('taskRunner', []);

taskRunner.controller('testCtrl', ['$scope', '$http',
  function($scope, $http) {
    /**
     * Task specification.
     * @type {!Array<{
     *   id: string,
     *   title: string,
     *   description: string,
     *   tableName: string,
     *   table: string,
     *   expected: string
     * }>}
     */
    $scope.tasks = [
      {
        id: 'all-taxes',
        title: 'All Taxes',
        description: 'Select all taxes.',
        tableName: 'taxi',
        table: 'name\tval\nhello\t123\nhello2\t456\n',
        expected: 'name\tval\nhello\t123\nhello2\t456\n'
      },
      {
        id: 'specific-taxis',
        title: 'Find Specific Taxis',
        description: 'Select the taxis with name "hello".',
        tableName: 'taxi',
        table: 'name\tval\nhello\t123\nhello2\t456\n',
        expected: 'name\tval\nhello\t123\n'
      }
    ];

    /**
     * Selected task index.
     * @type {string}
     */
    $scope.selected = '0';

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
      $scope.result = {
        processed: false
      };
    });

    $scope.submit = function() {
      $scope.running = true;
      $scope.connectError = '';
      $http.post('./run.php',{
        task: $scope.tasks[$scope.selected].id,
        source: $scope.source
      }).success(function(data) {
          data.processed = true;
          $scope.result = data;
          $scope.running = false;
        })
        .error(function() {
          $scope.connectError = 'Cannot connect to server';
          $scope.running = false;
        });
    };
  }
]);
