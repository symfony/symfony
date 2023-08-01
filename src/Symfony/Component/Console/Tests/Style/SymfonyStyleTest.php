<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Style;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

class SymfonyStyleTest extends TestCase
{
    protected Command $command;
    protected CommandTester $tester;
    private string|false $colSize;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        putenv('COLUMNS=121');
        $this->command = new Command('sfstyle');
        $this->tester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');
    }

    /**
     * @dataProvider inputCommandToOutputFilesProvider
     */
    public function testOutputs($inputCommandFilepath, $outputFilepath)
    {
        $code = require $inputCommandFilepath;
        $this->command->setCode($code);
        $this->tester->execute([], ['interactive' => false, 'decorated' => false]);
        $this->assertStringEqualsFile($outputFilepath, $this->tester->getDisplay(true));
    }

    /**
     * @dataProvider inputInteractiveCommandToOutputFilesProvider
     */
    public function testInteractiveOutputs($inputCommandFilepath, $outputFilepath)
    {
        $code = require $inputCommandFilepath;
        $this->command->setCode($code);
        $this->tester->execute([], ['interactive' => true, 'decorated' => false]);
        $this->assertStringEqualsFile($outputFilepath, $this->tester->getDisplay(true));
    }

    public static function inputInteractiveCommandToOutputFilesProvider()
    {
        $baseDir = __DIR__.'/../Fixtures/Style/SymfonyStyle';

        return array_map(null, glob($baseDir.'/command/interactive_command_*.php'), glob($baseDir.'/output/interactive_output_*.txt'));
    }

    public static function inputCommandToOutputFilesProvider()
    {
        $baseDir = __DIR__.'/../Fixtures/Style/SymfonyStyle';

        return array_map(null, glob($baseDir.'/command/command_*.php'), glob($baseDir.'/output/output_*.txt'));
    }

    public function testOutputProgressIterate()
    {
        $code = require __DIR__.'/../Fixtures/Style/SymfonyStyle/progress/command_progress_iterate.php';

        if ('\\' === \DIRECTORY_SEPARATOR || 'Hyper' === getenv('TERM_PROGRAM')) {
            $outputFilepath = __DIR__.'/../Fixtures/Style/SymfonyStyle/progress/output_progress_iterate_no_shade.txt';
        } else {
            $outputFilepath = __DIR__.'/../Fixtures/Style/SymfonyStyle/progress/output_progress_iterate_shade.txt';
        }

        $this->command->setCode($code);
        $this->tester->execute([], ['interactive' => false, 'decorated' => false]);
        $this->assertStringEqualsFile($outputFilepath, $this->tester->getDisplay(true));
    }

    public function testGetErrorStyle()
    {
        $input = $this->createMock(InputInterface::class);

        $errorOutput = $this->createMock(OutputInterface::class);
        $errorOutput
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());
        $errorOutput
            ->expects($this->once())
            ->method('write');

        $output = $this->createMock(ConsoleOutputInterface::class);
        $output
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());
        $output
            ->expects($this->once())
            ->method('getErrorOutput')
            ->willReturn($errorOutput);

        $io = new SymfonyStyle($input, $output);
        $io->getErrorStyle()->write('');
    }

    public function testCreateTableWithConsoleOutput()
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(ConsoleOutputInterface::class);
        $output
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());
        $output
            ->expects($this->once())
            ->method('section')
            ->willReturn($this->createMock(ConsoleSectionOutput::class));

        $style = new SymfonyStyle($input, $output);

        $style->createTable();
    }

    public function testCreateTableWithoutConsoleOutput()
    {
        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $output
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());

        $style = new SymfonyStyle($input, $output);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Output should be an instance of "Symfony\Component\Console\Output\ConsoleSectionOutput"');

        $style->createTable()->appendRow(['row']);
    }

    public function testGetErrorStyleUsesTheCurrentOutputIfNoErrorOutputIsAvailable()
    {
        $output = $this->createMock(OutputInterface::class);
        $output
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());

        $style = new SymfonyStyle($this->createMock(InputInterface::class), $output);

        $this->assertInstanceOf(SymfonyStyle::class, $style->getErrorStyle());
    }

    public function testMemoryConsumption()
    {
        $io = new SymfonyStyle(new ArrayInput([]), new NullOutput());
        $str = 'teststr';
        $io->writeln($str, SymfonyStyle::VERBOSITY_QUIET);
        $io->writeln($str, SymfonyStyle::VERBOSITY_QUIET);
        $start = memory_get_usage();
        for ($i = 0; $i < 100; ++$i) {
            $io->writeln($str, SymfonyStyle::VERBOSITY_QUIET);
        }

        $this->assertSame(0, memory_get_usage() - $start);
    }

    public function testAskAndClearExpectFullSectionCleared()
    {
        $answer = 'Answer';
        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, $answer.\PHP_EOL);
        rewind($inputStream);
        $input = $this->createMock(Input::class);
        $sections = [];
        $output = new ConsoleSectionOutput(fopen('php://memory', 'r+', false), $sections, StreamOutput::VERBOSITY_NORMAL, true, new OutputFormatter());
        $input
            ->method('isInteractive')
            ->willReturn(true);
        $input
            ->method('getStream')
            ->willReturn($inputStream);

        $style = new SymfonyStyle($input, $output);

        $style->writeln('start');
        $style->write('foo');
        $style->writeln(' and bar');
        $givenAnswer = $style->ask('Dummy question?');
        $style->write('foo2'.\PHP_EOL);
        $output->write('bar2');
        $output->clear();

        rewind($output->getStream());
        $this->assertEquals($answer, $givenAnswer);
        $this->assertEquals(
            'start'.\PHP_EOL. // write start
            'foo'.\PHP_EOL. // write foo
            "\x1b[1A\x1b[0Jfoo and bar".\PHP_EOL. // complete line
            \PHP_EOL.\PHP_EOL." \033[32mDummy question?\033[39m:".\PHP_EOL.' > '.\PHP_EOL.\PHP_EOL.\PHP_EOL. // question
            'foo2'.\PHP_EOL.\PHP_EOL. // write foo2
            'bar2'.\PHP_EOL. // write bar
            "\033[12A\033[0J", // clear 12 lines (11 output lines and one from the answer input return)
            stream_get_contents($output->getStream())
        );
    }

    /**
     * @group tty
     *
     * @dataProvider choiceDataProvider
     */
    public function testChoice(bool $multiple, bool $choiceKeys, array $choices, mixed $default, string $answer, string|array $givenAnswer, string $consoleString)
    {
        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, $answer.\PHP_EOL);
        rewind($inputStream);
        $input = $this->createMock(Input::class);
        $sections = [];
        $output = new ConsoleSectionOutput(fopen('php://memory', 'r+', false), $sections, StreamOutput::VERBOSITY_NORMAL, true, new OutputFormatter());
        $input
            ->method('isInteractive')
            ->willReturn(true);
        $input
            ->method('getStream')
            ->willReturn($inputStream);

        $style = new SymfonyStyle($input, $output);

        $result = $style->choice('Select choices', $choices, $default, $multiple, $choiceKeys);

        rewind($output->getStream());

        $this->assertEquals($givenAnswer, $result);
        $this->assertEquals(
            $consoleString,
            stream_get_contents($output->getStream())
        );
    }

    public static function choiceDataProvider()
    {
        return [
            [ // single choice with key input and value result
                false,
                false,
                [
                    0 => 'red',
                    1 => 'blue',
                    2 => 'orange',
                ],
                null,
                '1',
                'blue',
                \PHP_EOL.\PHP_EOL." \033[32mSelect choices\033[39m:".\PHP_EOL.
                "  [\033[33m0\033[39m] red".\PHP_EOL."  [\033[33m1\033[39m] blue".\PHP_EOL."  [\033[33m2\033[39m] orange".\PHP_EOL.
                implode(\PHP_EOL."\033[1A\033[0J", [
                    ' > ',
                    ' > 1',
                    " > 1\033[K",
                    " > 1\033[K\n",
                ]).\PHP_EOL.\PHP_EOL.\PHP_EOL,
            ],
            [ // single choice with default and key result
                false,
                true,
                [
                    0 => 'red',
                    1 => 'blue',
                    2 => 'orange',
                ],
                'orange',
                \PHP_EOL,
                '2',
                \PHP_EOL.\PHP_EOL." \033[32mSelect choices\033[39m [\033[33morange\033[39m]:".\PHP_EOL.
                "  [\033[33m0\033[39m] red".\PHP_EOL."  [\033[33m1\033[39m] blue".\PHP_EOL."  [\033[33m2\033[39m] orange".\PHP_EOL.
                implode(\PHP_EOL."\033[1A\033[0J", [
                    ' > ',
                    " > \n",
                ]).\PHP_EOL.\PHP_EOL.\PHP_EOL,
            ],
            [ // single choice with value input and value result
                false,
                false,
                [
                    0 => 'red',
                    1 => 'blue',
                    2 => 'orange',
                ],
                null,
                'red',
                'red',
                \PHP_EOL.\PHP_EOL." \033[32mSelect choices\033[39m:".\PHP_EOL.
                "  [\033[33m0\033[39m] red".\PHP_EOL."  [\033[33m1\033[39m] blue".\PHP_EOL."  [\033[33m2\033[39m] orange".\PHP_EOL.
                implode(\PHP_EOL."\033[1A\033[0J", [
                    ' > ',
                    ' > r',
                    " > r\033[K",
                    " > r\033[K\0337",
                    " > r\033[K\0337\033[30;47med\033[39;49m",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K\0337",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K\0337\033[30;47md\033[39;49m",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K\0337\033[30;47md\033[39;49m\0338",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K\0337\033[30;47md\033[39;49m\0338d",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K\0337\033[30;47md\033[39;49m\0338d\033[K",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K\0337\033[30;47md\033[39;49m\0338d\033[K\0337",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K\0337\033[30;47md\033[39;49m\0338d\033[K\0337",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K\0337\033[30;47md\033[39;49m\0338d\033[K\0337\0338",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K\0337\033[30;47md\033[39;49m\0338d\033[K\0337\0338",
                    " > r\033[K\0337\033[30;47med\033[39;49m\0338e\033[K\0337\033[30;47md\033[39;49m\0338d\033[K\0337\0338\n",
                ]).\PHP_EOL.\PHP_EOL.\PHP_EOL,
            ],
            [ // single choice with key input and key result
                false,
                true,
                [
                    0 => 'red',
                    1 => 'blue',
                    2 => 'orange',
                ],
                null,
                '1',
                '1',
                \PHP_EOL.\PHP_EOL." \033[32mSelect choices\033[39m:".\PHP_EOL.
                "  [\033[33m0\033[39m] red".\PHP_EOL."  [\033[33m1\033[39m] blue".\PHP_EOL."  [\033[33m2\033[39m] orange".\PHP_EOL.
                implode(\PHP_EOL."\033[1A\033[0J", [
                    ' > ',
                    ' > 1',
                    " > 1\033[K",
                    " > 1\033[K\n",
                ]).\PHP_EOL.\PHP_EOL.\PHP_EOL,
            ],
            [ // multi choice with key input and value result
                true,
                false,
                [
                    0 => 'red',
                    1 => 'blue',
                    2 => 'orange',
                ],
                null,
                '1,2',
                ['blue', 'orange'],
                \PHP_EOL.\PHP_EOL." \033[32mSelect choices\033[39m:".\PHP_EOL.
                "  [\033[33m0\033[39m] red".\PHP_EOL."  [\033[33m1\033[39m] blue".\PHP_EOL."  [\033[33m2\033[39m] orange".\PHP_EOL.
                implode(\PHP_EOL."\033[1A\033[0J", [
                    ' > ',
                    ' > 1',
                    " > 1\033[K",
                    " > 1\033[K,",
                    " > 1\033[K,\033[K",
                    " > 1\033[K,\033[K2",
                    " > 1\033[K,\033[K2\033[K",
                    " > 1\033[K,\033[K2\033[K\n",
                ]).\PHP_EOL.\PHP_EOL.\PHP_EOL,
            ],
            [ // multi choice with key input and key result
                true,
                true,
                [
                    0 => 'red',
                    1 => 'blue',
                    2 => 'orange',
                ],
                null,
                '1,2',
                [1, 2],
                \PHP_EOL.\PHP_EOL." \033[32mSelect choices\033[39m:".\PHP_EOL.
                "  [\033[33m0\033[39m] red".\PHP_EOL."  [\033[33m1\033[39m] blue".\PHP_EOL."  [\033[33m2\033[39m] orange".\PHP_EOL.
                implode(\PHP_EOL."\033[1A\033[0J", [
                    ' > ',
                    ' > 1',
                    " > 1\033[K",
                    " > 1\033[K,",
                    " > 1\033[K,\033[K",
                    " > 1\033[K,\033[K2",
                    " > 1\033[K,\033[K2\033[K",
                    " > 1\033[K,\033[K2\033[K\n",
                ]).\PHP_EOL.\PHP_EOL.\PHP_EOL,
            ],
            [ // single choice with association always returns the key
                false,
                false,
                [
                    'k0' => 'value_0',
                    'k1' => 'value_1',
                    'k2' => 'value_2',
                ],
                null,
                'k1',
                'k1',
                \PHP_EOL.\PHP_EOL." \033[32mSelect choices\033[39m:".\PHP_EOL.
                "  [\033[33mk0\033[39m] value_0".\PHP_EOL."  [\033[33mk1\033[39m] value_1".\PHP_EOL."  [\033[33mk2\033[39m] value_2".\PHP_EOL.
                implode(\PHP_EOL."\033[1A\033[0J", [
                    ' > ',
                    ' > k',
                    " > k\033[K",
                    " > k\033[K\0337",
                    " > k\033[K\0337\033[30;47m0\033[39;49m",
                    " > k\033[K\0337\033[30;47m0\033[39;49m\0338",
                    " > k\033[K\0337\033[30;47m0\033[39;49m\03381",
                    " > k\033[K\0337\033[30;47m0\033[39;49m\03381\033[K",
                    " > k\033[K\0337\033[30;47m0\033[39;49m\03381\033[K\0337",
                    " > k\033[K\0337\033[30;47m0\033[39;49m\03381\033[K\0337",
                    " > k\033[K\0337\033[30;47m0\033[39;49m\03381\033[K\0337\0338",
                    " > k\033[K\0337\033[30;47m0\033[39;49m\03381\033[K\0337\0338",
                    " > k\033[K\0337\033[30;47m0\033[39;49m\03381\033[K\0337\0338\n",
                ]).\PHP_EOL.\PHP_EOL.\PHP_EOL,
            ],
        ];
    }
}
