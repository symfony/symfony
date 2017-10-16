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
    }

    public function testCallingOtherErrorHandler()
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException('PHPUnit\Framework\Error\Warning');
            $this->expectExceptionMessage('Test that PHPUnit\'s error handler fires.');
        } else {
            $this->setExpectedException('PHPUnit_Framework_Error_Warning', 'Test that PHPUnit\'s error handler fires.');
        }

        trigger_error('Test that PHPUnit\'s error handler fires.', E_USER_WARNING);
    }
}
