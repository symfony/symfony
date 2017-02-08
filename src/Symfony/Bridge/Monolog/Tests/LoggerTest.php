<?php

namespace Symfony\Bridge\Monolog\Tests;

use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Handler\DebugHandler;
use Symfony\Bridge\Monolog\Logger;

class LoggerTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testEmerg()
    {
        $handler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($handler);

        $this->assertTrue($logger->emerg('test'));
        $this->assertTrue($handler->hasEmergency('test'));
    }

    /**
     * @group legacy
     */
    public function testCrit()
    {
        $handler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($handler);

        $this->assertTrue($logger->crit('test'));
        $this->assertTrue($handler->hasCritical('test'));
    }

    /**
     * @group legacy
     */
    public function testErr()
    {
        $handler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($handler);

        $this->assertTrue($logger->err('test'));
        $this->assertTrue($handler->hasError('test'));
    }

    /**
     * @group legacy
     */
    public function testWarn()
    {
        $handler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($handler);

        $this->assertTrue($logger->warn('test'));
        $this->assertTrue($handler->hasWarning('test'));
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

    public function testGetLogsWithoutDebugHandler()
    {
        $logger = new Logger('test');
        $logger->pushHandler(new TestHandler());
        $logger->addInfo('test');

        $this->assertSame(array(), $logger->getLogs());
    }

    public function testCountErrors()
    {
        $logger = new Logger('test');
        $logger->pushHandler(new DebugHandler());

        $logger->addInfo('test');
        $logger->addError('uh-oh');

        $this->assertEquals(1, $logger->countErrors());
    }

    public function testCountErrorsWithoutDebugHandler()
    {
        $logger = new Logger('test');
        $logger->pushHandler(new TestHandler());

        $logger->addInfo('test');
        $logger->addError('uh-oh');

        $this->assertEquals(0, $logger->countErrors());
    }
}
