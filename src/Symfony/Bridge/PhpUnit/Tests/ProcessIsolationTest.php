<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    public function testIsolation()
    {
        @trigger_error('Test abc', \E_USER_DEPRECATED);
        $this->addToAssertionCount(1);
    }

    public function testCallingOtherErrorHandler()
    {
        $this->expectException(\PHPUnit\Framework\Exception::class);
        $this->expectExceptionMessage('Test that PHPUnit\'s error handler fires.');

        trigger_error('Test that PHPUnit\'s error handler fires.', \E_USER_WARNING);
    }
}
