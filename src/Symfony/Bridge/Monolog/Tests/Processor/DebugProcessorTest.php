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
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class DebugProcessorTest extends TestCase
{
    /**
     * @dataProvider providerDatetimeFormatTests
     */
    public function testDatetimeFormat(array $record, $expectedTimestamp)
    {
        $processor = new DebugProcessor();
        $processor($record);

        $records = $processor->getLogs();
        self::assertCount(1, $records);
        self::assertSame($expectedTimestamp, $records[0]['timestamp']);
    }

    public function providerDatetimeFormatTests(): array
    {
        $record = self::getRecord();

        return [
            [array_merge($record, ['datetime' => new \DateTime('2019-01-01T00:01:00+00:00')]), 1546300860],
            [array_merge($record, ['datetime' => '2019-01-01T00:01:00+00:00']), 1546300860],
            [array_merge($record, ['datetime' => 'foo']), false],
        ];
    }

    /**
     * @dataProvider providerDatetimeRfc3339FormatTests
     */
    public function testDatetimeRfc3339Format(array $record, $expectedTimestamp)
    {
        $processor = new DebugProcessor();
        $processor($record);

        $records = $processor->getLogs();
        self::assertCount(1, $records);
        self::assertSame($expectedTimestamp, $records[0]['timestamp_rfc3339']);
    }

    public function providerDatetimeRfc3339FormatTests(): array
    {
        $record = self::getRecord();

        return [
            [array_merge($record, ['datetime' => new \DateTime('2019-01-01T00:01:00+00:00')]), '2019-01-01T00:01:00.000+00:00'],
            [array_merge($record, ['datetime' => '2019-01-01T00:01:00+00:00']), '2019-01-01T00:01:00.000+00:00'],
            [array_merge($record, ['datetime' => 'foo']), false],
        ];
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

    private static function getRecord($level = Logger::WARNING, $message = 'test'): array
    {
        return [
            'message' => $message,
            'context' => [],
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'test',
            'datetime' => new \DateTime(),
            'extra' => [],
        ];
    }
}
