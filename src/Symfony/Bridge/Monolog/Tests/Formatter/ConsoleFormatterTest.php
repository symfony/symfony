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
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\VarDumper\Cloner\VarCloner;

class ConsoleFormatterTest extends TestCase
{
    /**
     * @dataProvider providerFormatTests
     */
    public function testFormat(array $record, $expectedMessage)
    {
        $formatter = new ConsoleFormatter();
        self::assertSame($expectedMessage, $formatter->format($record));
    }

    public static function providerFormatTests(): array
    {
        $currentDateTime = new \DateTime();

        return [
            'record with DateTime object in datetime field' => [
                'record' => [
                    'message' => 'test',
                    'context' => [],
                    'level' => Logger::WARNING,
                    'level_name' => Logger::getLevelName(Logger::WARNING),
                    'channel' => 'test',
                    'datetime' => $currentDateTime,
                    'extra' => [],
                ],
                'expectedMessage' => sprintf(
                    "%s <fg=cyan>WARNING  </> <comment>[test]</> test\n",
                    $currentDateTime->format(ConsoleFormatter::SIMPLE_DATE)
                ),
            ],
            'record with string in datetime field' => [
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
            ],
        ];
    }

    public function testItEscapesTheRecordFields()
    {
        $formatter = new ConsoleFormatter(['colors' => false]);

        $record = self::createRecord([
            'message' => "SELECT * FROM t WHERE a < 1 AND b > 2\033[2J\u{9b}2J",
            'channel' => '<fg=red>app</>',
        ]);

        $output = $formatter->format($record);

        $this->assertStringNotContainsString("\033", $output);
        $this->assertStringNotContainsString("\u{9b}", $output);
        $this->assertStringContainsString('['.OutputFormatter::escape('<fg=red>app</>').']', $output);
        $this->assertStringContainsString(OutputFormatter::escape('SELECT * FROM t WHERE a < 1 AND b > 2'), $output);
    }

    public function testItEscapesTheDumpedContext()
    {
        $formatter = new ConsoleFormatter(['colors' => false]);

        $record = self::createRecord(['context' => ['user' => '<fg=red>alice</>']]);

        $this->assertStringContainsString(OutputFormatter::escape('"user" => "<fg=red>alice</>"'), $formatter->format($record));
    }

    public function testItKeepsTheMarkupAroundReplacedPlaceholders()
    {
        $formatter = new ConsoleFormatter(['colors' => false]);

        $record = self::createRecord(['message' => 'Hello {user}', 'context' => ['user' => '<fg=red>alice</>']]);

        $this->assertStringContainsString('Hello <comment>'.OutputFormatter::escape('<fg=red>alice</>').'</>', $formatter->format($record));
    }

    public function testPlaceholderInMessageWithDataContext()
    {
        $context = (new VarCloner())->cloneVar(['user' => 'alice']);
        $formatter = new ConsoleFormatter(['colors' => false]);

        $output = $formatter->format([
            'message' => 'Hello {user}',
            'context' => $context,
            'level' => Logger::WARNING,
            'level_name' => Logger::getLevelName(Logger::WARNING),
            'channel' => 'test',
            'datetime' => '2019-01-01T00:42:00+00:00',
            'extra' => [],
        ]);

        self::assertStringContainsString('Hello <comment>alice</>', $output);
    }

    private static function createRecord(array $record): array
    {
        return $record + [
            'message' => 'test',
            'context' => [],
            'level' => Logger::WARNING,
            'level_name' => Logger::getLevelName(Logger::WARNING),
            'channel' => 'test',
            'datetime' => new \DateTime(),
            'extra' => [],
        ];
    }
}
