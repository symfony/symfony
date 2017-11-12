<?php

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;

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
     * @expectedDeprecation Test abc
     */
    public function testIsolation(): void
    {
        @trigger_error('Test abc', E_USER_DEPRECATED);
        $this->addToAssertionCount(1);
    }

    public function testCallingOtherErrorHandler(): void
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
