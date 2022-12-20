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
use Monolog\ResettableInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Component\HttpFoundation\Request;

class LoggerTest extends TestCase
{
    public function testGetLogsWithoutDebugProcessor()
    {
        $handler = new TestHandler();
        $logger = new Logger(__METHOD__, [$handler]);

        $logger->error('error message');
        self::assertSame([], $logger->getLogs());
    }

    public function testCountErrorsWithoutDebugProcessor()
    {
        $handler = new TestHandler();
        $logger = new Logger(__METHOD__, [$handler]);

        $logger->error('error message');
        self::assertSame(0, $logger->countErrors());
    }

    public function testGetLogsWithDebugProcessor()
    {
        $handler = new TestHandler();
        $processor = new DebugProcessor();
        $logger = new Logger(__METHOD__, [$handler], [$processor]);

        $logger->error('error message');
        self::assertCount(1, $logger->getLogs());
    }

    public function testCountErrorsWithDebugProcessor()
    {
        $handler = new TestHandler();
        $processor = new DebugProcessor();
        $logger = new Logger(__METHOD__, [$handler], [$processor]);

        $logger->debug('test message');
        $logger->info('test message');
        $logger->notice('test message');
        $logger->warning('test message');

        $logger->error('test message');
        $logger->critical('test message');
        $logger->alert('test message');
        $logger->emergency('test message');

        self::assertSame(4, $logger->countErrors());
    }

    public function testGetLogsWithDebugProcessor2()
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $logger->pushProcessor(new DebugProcessor());

        $logger->info('test');
        self::assertCount(1, $logger->getLogs());
        [$record] = $logger->getLogs();

        self::assertEquals('test', $record['message']);
        self::assertEquals(Logger::INFO, $record['priority']);
    }

    public function testGetLogsWithDebugProcessor3()
    {
        $request = new Request();
        $processor = self::createMock(DebugProcessor::class);
        $processor->expects(self::once())->method('getLogs')->with($request);
        $processor->expects(self::once())->method('countErrors')->with($request);

        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $logger->pushProcessor($processor);

        $logger->getLogs($request);
        $logger->countErrors($request);
    }

    public function testClear()
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $logger->pushProcessor(new DebugProcessor());

        $logger->info('test');
        $logger->clear();

        self::assertEmpty($logger->getLogs());
        self::assertSame(0, $logger->countErrors());
    }

    public function testReset()
    {
        $handler = new TestHandler();
        $logger = new Logger('test', [$handler]);
        $logger->pushProcessor(new DebugProcessor());

        $logger->info('test');
        $logger->reset();

        self::assertEmpty($logger->getLogs());
        self::assertSame(0, $logger->countErrors());
        if (class_exists(ResettableInterface::class)) {
            self::assertEmpty($handler->getRecords());
        }
    }

    public function testInheritedClassCallGetLogsWithoutArgument()
    {
        $loggerChild = new ClassThatInheritLogger('test');
        self::assertSame([], $loggerChild->getLogs());
    }

    public function testInheritedClassCallCountErrorsWithoutArgument()
    {
        $loggerChild = new ClassThatInheritLogger('test');
        self::assertEquals(0, $loggerChild->countErrors());
    }
}
