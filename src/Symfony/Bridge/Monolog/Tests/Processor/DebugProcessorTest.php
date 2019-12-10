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

    /**
     * @return array
     */
    public function providerDatetimeFormatTests()
    {
        $record = $this->getRecord();

        return [
            [array_merge($record, ['datetime' => new \DateTime('2019-01-01T00:01:00+00:00')]), 1546300860],
            [array_merge($record, ['datetime' => '2019-01-01T00:01:00+00:00']), 1546300860],
            [array_merge($record, ['datetime' => 'foo']), false],
        ];
    }

    /**
     * @return array
     */
    private function getRecord()
    {
        return [
            'message' => 'test',
            'context' => [],
            'level' => Logger::DEBUG,
            'level_name' => Logger::getLevelName(Logger::DEBUG),
            'channel' => 'test',
            'datetime' => new \DateTime(),
        ];
    }
}
