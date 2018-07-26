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
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Component\HttpFoundation\Request;

class LoggerTest extends TestCase
{
    public function testGetLogsWithoutDebugProcessor()
    {
        $handler = new TestHandler();
        $logger = new Logger(__METHOD__, array($handler));

        $this->assertTrue($logger->error('error message'));
        $this->assertSame(array(), $logger->getLogs());
    }

    public function testCountErrorsWithoutDebugProcessor()
    {
        $handler = new TestHandler();
        $logger = new Logger(__METHOD__, array($handler));

        $this->assertTrue($logger->error('error message'));
        $this->assertSame(0, $logger->countErrors());
    }

    public function testGetLogsWithDebugProcessor()
    {
        $handler = new TestHandler();
        $processor = new DebugProcessor();
        $logger = new Logger(__METHOD__, array($handler), array($processor));

        $this->assertTrue($logger->error('error message'));
        $this->assertCount(1, $logger->getLogs());
    }

    public function testCountErrorsWithDebugProcessor()
    {
        $handler = new TestHandler();
        $processor = new DebugProcessor();
        $logger = new Logger(__METHOD__, array($handler), array($processor));

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

    public function testGetLogsWithDebugProcessor2()
    {
        $handler = new TestHandler();
        $logger = new Logger('test', array($handler));
        $logger->pushProcessor(new DebugProcessor());

        $logger->addInfo('test');
        $this->assertCount(1, $logger->getLogs());
        list($record) = $logger->getLogs();

        $this->assertEquals('test', $record['message']);
        $this->assertEquals(Logger::INFO, $record['priority']);
    }

    public function testGetLogsWithDebugProcessor3()
    {
        $request = new Request();
        $processor = $this->getMockBuilder(DebugProcessor::class)->getMock();
        $processor->expects($this->once())->method('getLogs')->with($request);
        $processor->expects($this->once())->method('countErrors')->with($request);

        $handler = new TestHandler();
        $logger = new Logger('test', array($handler));
        $logger->pushProcessor($processor);

        $logger->getLogs($request);
        $logger->countErrors($request);
    }

    public function testClear()
    {
        $handler = new TestHandler();
        $logger = new Logger('test', array($handler));
        $logger->pushProcessor(new DebugProcessor());

        $logger->addInfo('test');
        $logger->clear();

        $this->assertEmpty($logger->getLogs());
        $this->assertSame(0, $logger->countErrors());
    }
}
