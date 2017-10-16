<?php

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

/**
 * Don't remove this test case, it tests the legacy group.
 *
 * @group legacy
 *
 * @runTestsInSeparateProcesses
 */
class ProcessIsolationTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        // Ensure we are using the deprecation error handler. Unfortunately the code in bootstrap.php does not appear to
        // be working.
        DeprecationErrorHandler::collectDeprecations(getenv('SYMFONY_DEPRECATIONS_SERIALIZE'));
        parent::setUp();
    }

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
