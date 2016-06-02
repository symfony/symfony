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

use Monolog\Handler\TestHandler;
use Symfony\Bridge\Monolog\Handler\DebugHandler;
use Symfony\Bridge\Monolog\Logger;

class LoggerTest extends \PHPUnit_Framework_TestCase
{
    public function testGetLogsWithDebugHandler()
    {
        $handler = new DebugHandler();
        $logger = new Logger(__METHOD__, array($handler));

        $this->assertTrue($logger->error('error message'));
        $this->assertSame(1, count($logger->getLogs()));
    }

    public function testGetLogsWithoutDebugHandler()
    {
        $handler = new TestHandler();
        $logger = new Logger(__METHOD__, array($handler));

        $this->assertTrue($logger->error('error message'));
        $this->assertSame(array(), $logger->getLogs());
    }

    public function testCountErrorsWithDebugHandler()
    {
        $handler = new DebugHandler();
        $logger = new Logger(__METHOD__, array($handler));

        $this->assertTrue($logger->debug('test message'));
        $this->assertTrue($logger->info('test message'));
        $this->assertTrue($logger->notice('test message'));
        $this->assertTrue($logger->warning('test message'));

        $this->assertTrue($logger->error('test message'));
        $this->assertTrue($logger->critical('test message'));
        $this->assertTrue($logger->alert('test message'));
        $this->assertTrue($logger->emergency('test message'));

        $this->assertSame(4, $logger->countErrors());
    }

    public function testGetLogs()
    {
        $logger = new Logger('test');
        $logger->pushHandler(new DebugHandler());

        $logger->addInfo('test');
        $this->assertCount(1, $logger->getLogs());
        list($record) = $logger->getLogs();

        $this->assertEquals('test', $record['message']);
        $this->assertEquals(Logger::INFO, $record['priority']);
    }

    public function testCountErrorsWithoutDebugHandler()
    {
        $handler = new TestHandler();
        $logger = new Logger(__METHOD__, array($handler));

        $this->assertTrue($logger->error('error message'));
        $this->assertSame(0, $logger->countErrors());
    }
}
