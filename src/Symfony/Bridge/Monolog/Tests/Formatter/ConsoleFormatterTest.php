<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Formatter;

use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symfony\Bridge\Monolog\Tests\RecordFactory;

class ConsoleFormatterTest extends TestCase
{
    /**
     * @dataProvider providerFormatTests
     */
    public function testFormat(array|LogRecord $record, $expectedMessage)
    {
        $formatter = new ConsoleFormatter();
        self::assertSame($expectedMessage, $formatter->format($record));
    }

    public static function providerFormatTests(): array
    {
        $currentDateTime = new \DateTimeImmutable();

        $tests = [
            'record with DateTime object in datetime field' => [
                'record' => RecordFactory::create(datetime: $currentDateTime),
                'expectedMessage' => sprintf(
                    "%s <fg=cyan>WARNING  </> <comment>[test]</> test\n",
                    $currentDateTime->format(ConsoleFormatter::SIMPLE_DATE)
                ),
            ],
        ];

        if (Logger::API < 3) {
            $tests['record with string in datetime field'] = [
                'record' => [
                    'message' => 'test',
                    'context' => [],
                    'level' => Logger::WARNING,
                    'level_name' => Logger::getLevelName(Logger::WARNING),
                    'channel' => 'test',
                    'datetime' => '2019-01-01T00:42:00+00:00',
                    'extra' => [],
                ],
                'expectedMessage' => "2019-01-01T00:42:00+00:00 <fg=cyan>WARNING  </> <comment>[test]</> test\n",
            ];
        }

        return $tests;
    }
}
