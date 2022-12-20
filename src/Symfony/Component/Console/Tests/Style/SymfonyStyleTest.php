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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

class SymfonyStyleTest extends TestCase
{
    /** @var Command */
    protected $command;
    /** @var CommandTester */
    protected $tester;
    private $colSize;

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
        $this->command = null;
        $this->tester = null;
    }

    /**
     * @dataProvider inputCommandToOutputFilesProvider
     */
    public function testOutputs($inputCommandFilepath, $outputFilepath)
    {
        $code = require $inputCommandFilepath;
        $this->command->setCode($code);
        $this->tester->execute([], ['interactive' => false, 'decorated' => false]);
        self::assertStringEqualsFile($outputFilepath, $this->tester->getDisplay(true));
    }

    /**
     * @dataProvider inputInteractiveCommandToOutputFilesProvider
     */
    public function testInteractiveOutputs($inputCommandFilepath, $outputFilepath)
    {
        $code = require $inputCommandFilepath;
        $this->command->setCode($code);
        $this->tester->execute([], ['interactive' => true, 'decorated' => false]);
        self::assertStringEqualsFile($outputFilepath, $this->tester->getDisplay(true));
    }

    public function inputInteractiveCommandToOutputFilesProvider()
    {
        $baseDir = __DIR__.'/../Fixtures/Style/SymfonyStyle';

        return array_map(null, glob($baseDir.'/command/interactive_command_*.php'), glob($baseDir.'/output/interactive_output_*.txt'));
    }

    public function inputCommandToOutputFilesProvider()
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
        self::assertStringEqualsFile($outputFilepath, $this->tester->getDisplay(true));
    }

    public function testGetErrorStyle()
    {
        $input = self::createMock(InputInterface::class);

        $errorOutput = self::createMock(OutputInterface::class);
        $errorOutput
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());
        $errorOutput
            ->expects(self::once())
            ->method('write');

        $output = self::createMock(ConsoleOutputInterface::class);
        $output
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());
        $output
            ->expects(self::once())
            ->method('getErrorOutput')
            ->willReturn($errorOutput);

        $io = new SymfonyStyle($input, $output);
        $io->getErrorStyle()->write('');
    }

    public function testCreateTableWithConsoleOutput()
    {
        $input = self::createMock(InputInterface::class);
        $output = self::createMock(ConsoleOutputInterface::class);
        $output
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());
        $output
            ->expects(self::once())
            ->method('section')
            ->willReturn(self::createMock(ConsoleSectionOutput::class));

        $style = new SymfonyStyle($input, $output);

        $style->createTable();
    }

    public function testCreateTableWithoutConsoleOutput()
    {
        $input = self::createMock(InputInterface::class);
        $output = self::createMock(OutputInterface::class);
        $output
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());

        $style = new SymfonyStyle($input, $output);

        self::expectException(RuntimeException::class);
        self::expectDeprecationMessage('Output should be an instance of "Symfony\Component\Console\Output\ConsoleSectionOutput"');

        $style->createTable()->appendRow(['row']);
    }

    public function testGetErrorStyleUsesTheCurrentOutputIfNoErrorOutputIsAvailable()
    {
        $output = self::createMock(OutputInterface::class);
        $output
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());

        $style = new SymfonyStyle(self::createMock(InputInterface::class), $output);

        self::assertInstanceOf(SymfonyStyle::class, $style->getErrorStyle());
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

        self::assertSame(0, memory_get_usage() - $start);
    }
}
