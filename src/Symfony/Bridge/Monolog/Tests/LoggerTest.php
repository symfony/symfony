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

class LoggerTest extends \PHPUnit_Framework_TestCase
{
    public function testGetLogsWithDebugHandler()
    {
        $expectedLogs = array('foo', 'bar');

        $debugHandler = $this->getMock('Symfony\Component\HttpKernel\Log\DebugLoggerInterface');
        $debugHandler
            ->expects($this->any())
            ->method('getLogs')
            ->will($this->returnValue($expectedLogs))
        ;

        $logger = new Logger('foobar', array($debugHandler));
        $this->assertEquals($expectedLogs, $logger->getLogs());
    }

    public function testGetLogsWithoutDebugHandler()
    {
        $handler = $this->getMock('Symfony\Component\HttpKernel\Log\LoggerInterface');

        $logger = new Logger('foobar', array($handler));
        $this->assertEquals(array(), $logger->getLogs());
    }

    public function testCountErrorsWithDebugHandler()
    {
        $debugHandler = $this->getMock('Symfony\Component\HttpKernel\Log\DebugLoggerInterface');
        $debugHandler
            ->expects($this->any())
            ->method('countErrors')
            ->will($this->returnValue(5))
        ;

        $logger = new Logger('foobar', array($debugHandler));
        $this->assertEquals(5, $logger->countErrors());
    }

    public function testCountErrorsWithoutDebugHandler()
    {
        $handler = $this->getMock('Symfony\Component\HttpKernel\Log\LoggerInterface');

        $logger = new Logger('foobar', array($handler));
        $this->assertEquals(0, $logger->countErrors());
    }
}
