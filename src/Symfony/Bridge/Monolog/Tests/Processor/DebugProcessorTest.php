<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Processor;

use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Bridge\Monolog\Tests\RecordFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class DebugProcessorTest extends TestCase
{
    public function testDatetimeFormat()
    {
        $record = RecordFactory::create(datetime: new \DateTimeImmutable('2019-01-01T00:01:00+00:00'));
        $processor = new DebugProcessor();
        $processor($record);

        $records = $processor->getLogs();
        self::assertCount(1, $records);
        self::assertSame(1546300860, $records[0]['timestamp']);
    }

    public function testDatetimeRfc3339Format()
    {
        $record = RecordFactory::create(datetime: new \DateTimeImmutable('2019-01-01T00:01:00+00:00'));
        $processor = new DebugProcessor();
        $processor($record);

        $records = $processor->getLogs();
        self::assertCount(1, $records);
        self::assertSame('2019-01-01T00:01:00.000+00:00', $records[0]['timestamp_rfc3339']);
    }

    public function testDebugProcessor()
    {
        $processor = new DebugProcessor();
        $processor(self::getRecord());
        $processor(self::getRecord(Logger::ERROR));

        $this->assertCount(2, $processor->getLogs());
        $this->assertSame(1, $processor->countErrors());
    }

    public function testDebugProcessorWithoutLogs()
    {
        $processor = new DebugProcessor();

        $this->assertCount(0, $processor->getLogs());
        $this->assertSame(0, $processor->countErrors());
    }

    public function testWithRequestStack()
    {
        $stack = new RequestStack();
        $processor = new DebugProcessor($stack);
        $processor(self::getRecord());
        $processor(self::getRecord(Logger::ERROR));

        $this->assertCount(2, $processor->getLogs());
        $this->assertSame(1, $processor->countErrors());

        $request = new Request();
        $stack->push($request);

        $processor(self::getRecord());
        $processor(self::getRecord(Logger::ERROR));

        $this->assertCount(4, $processor->getLogs());
        $this->assertSame(2, $processor->countErrors());

        $this->assertCount(2, $processor->getLogs($request));
        $this->assertSame(1, $processor->countErrors($request));

        $this->assertCount(0, $processor->getLogs(new Request()));
        $this->assertSame(0, $processor->countErrors(new Request()));
    }

    public function testInheritedClassCallGetLogsWithoutArgument()
    {
        $debugProcessorChild = new ClassThatInheritDebugProcessor();
        $this->assertSame([], $debugProcessorChild->getLogs());
    }

    public function testInheritedClassCallCountErrorsWithoutArgument()
    {
        $debugProcessorChild = new ClassThatInheritDebugProcessor();
        $this->assertEquals(0, $debugProcessorChild->countErrors());
    }

    private static function getRecord($level = Logger::WARNING, $message = 'test'): array|LogRecord
    {
        return RecordFactory::create($level, $message);
    }
}
