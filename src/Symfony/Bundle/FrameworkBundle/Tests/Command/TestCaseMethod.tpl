<?php
set_include_path('{include_path}');
require_once 'PHPUnit/Autoload.php';
ob_start();

function __phpunit_run_isolated_test()
{
    if (!class_exists('{className}')) {
        require_once '{filename}';
    }

    $result = new PHPUnit_Framework_TestResult;
    $result->collectRawCodeCoverageInformation({collectCodeCoverageInformation});

    $test = new {className}('{methodName}', unserialize('{data}'), '{dataName}');
    $test->setDependencyInput(unserialize('{dependencyInput}'));
    $test->setInIsolation(TRUE);

    ob_end_clean();
    ob_start();
    $test->run($result);
    $output = ob_get_clean();

    print serialize(
      array(
        'testResult'    => $test->getResult(),
        'numAssertions' => $test->getNumAssertions(),
        'result'        => $result,
        'output'        => $output
      )
    );

    ob_start();
}

{globals}

if (isset($GLOBALS['__PHPUNIT_BOOTSTRAP'])) {
    require_once $GLOBALS['__PHPUNIT_BOOTSTRAP'];
    unset($GLOBALS['__PHPUNIT_BOOTSTRAP']);
}

{constants}

__phpunit_run_isolated_test();
ob_end_clean();
?>
