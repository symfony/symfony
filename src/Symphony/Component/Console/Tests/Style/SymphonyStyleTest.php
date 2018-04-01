<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Console\Tests\Style;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Tester\CommandTester;
use Symphony\Component\Console\Formatter\OutputFormatter;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Output\ConsoleOutputInterface;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Style\SymphonyStyle;

class SymphonyStyleTest extends TestCase
{
    /** @var Command */
    protected $command;
    /** @var CommandTester */
    protected $tester;

    protected function setUp()
    {
        putenv('COLUMNS=121');
        $this->command = new Command('sfstyle');
        $this->tester = new CommandTester($this->command);
    }

    protected function tearDown()
    {
        putenv('COLUMNS');
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
        $this->tester->execute(array(), array('interactive' => false, 'decorated' => false));
        $this->assertStringEqualsFile($outputFilepath, $this->tester->getDisplay(true));
    }

    /**
     * @dataProvider inputInteractiveCommandToOutputFilesProvider
     */
    public function testInteractiveOutputs($inputCommandFilepath, $outputFilepath)
    {
        $code = require $inputCommandFilepath;
        $this->command->setCode($code);
        $this->tester->execute(array(), array('interactive' => true, 'decorated' => false));
        $this->assertStringEqualsFile($outputFilepath, $this->tester->getDisplay(true));
    }

    public function inputInteractiveCommandToOutputFilesProvider()
    {
        $baseDir = __DIR__.'/../Fixtures/Style/SymphonyStyle';

        return array_map(null, glob($baseDir.'/command/interactive_command_*.php'), glob($baseDir.'/output/interactive_output_*.txt'));
    }

    public function inputCommandToOutputFilesProvider()
    {
        $baseDir = __DIR__.'/../Fixtures/Style/SymphonyStyle';

        return array_map(null, glob($baseDir.'/command/command_*.php'), glob($baseDir.'/output/output_*.txt'));
    }

    public function testGetErrorStyle()
    {
        $input = $this->getMockBuilder(InputInterface::class)->getMock();

        $errorOutput = $this->getMockBuilder(OutputInterface::class)->getMock();
        $errorOutput
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());
        $errorOutput
            ->expects($this->once())
            ->method('write');

        $output = $this->getMockBuilder(ConsoleOutputInterface::class)->getMock();
        $output
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());
        $output
            ->expects($this->once())
            ->method('getErrorOutput')
            ->willReturn($errorOutput);

        $io = new SymphonyStyle($input, $output);
        $io->getErrorStyle()->write('');
    }

    public function testGetErrorStyleUsesTheCurrentOutputIfNoErrorOutputIsAvailable()
    {
        $output = $this->getMockBuilder(OutputInterface::class)->getMock();
        $output
            ->method('getFormatter')
            ->willReturn(new OutputFormatter());

        $style = new SymphonyStyle($this->getMockBuilder(InputInterface::class)->getMock(), $output);

        $this->assertInstanceOf(SymphonyStyle::class, $style->getErrorStyle());
    }
}
