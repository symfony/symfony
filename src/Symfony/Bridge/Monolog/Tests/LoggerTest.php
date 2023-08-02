<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests;

use Symfony\Bridge\Monolog\Logger;

/**
 * @group legacy
 */
class LoggerTest extends MonologTest
{
    public function testPublicApi()
    {
        // We override the parent test because it's not relevant for legacy
        // tests
        $this->assertTrue(true);
    }

    public function testInheritedClassCallGetLogsWithoutArgument()
    {
        $loggerChild = new ClassThatInheritLogger('test');
        $this->assertSame([], $loggerChild->getLogs());
    }

    public function testInheritedClassCallCountErrorsWithoutArgument()
    {
        $loggerChild = new ClassThatInheritLogger('test');
        $this->assertEquals(0, $loggerChild->countErrors());
    }

    protected function createLogger($name, array $handlers = [], array $processors = [])
    {
        return new Logger($name, $handlers, $processors);
    }
}
