<?php

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Don't remove this test case, it tests the legacy group.
 *
 * @group legacy
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * Note that for the deprecation handler to work in a separate process we need to disable the preservation of global
 * state. This is because composer's autoloader stores which files have been autoloaded in the global
 * '__composer_autoload_files'. If this is preserved then bootstrap.php will not run again meaning that deprecations
 * won't be collected.
 */
class ProcessIsolationTest extends TestCase
{

    /**
     * @expectedDeprecation Test abc
     */
    public function testIsolation()
    {
        @trigger_error('Test abc', E_USER_DEPRECATED);
        $this->addToAssertionCount(1);
    }

    public function testCallingOtherErrorHandler()
    {
        $class = class_exists('PHPUnit\Framework\Exception') ? 'PHPUnit\Framework\Exception' : 'PHPUnit_Framework_Exception';
        if (method_exists($this, 'expectException')) {
            $this->expectException($class);
            $this->expectExceptionMessage('Test that PHPUnit\'s error handler fires.');
        } else {
            $this->setExpectedException($class, 'Test that PHPUnit\'s error handler fires.');
        }

        trigger_error('Test that PHPUnit\'s error handler fires.', E_USER_WARNING);
    }
}
