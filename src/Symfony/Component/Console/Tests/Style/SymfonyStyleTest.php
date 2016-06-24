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

use PHPUnit_Framework_TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

class SymfonyStyleTest extends PHPUnit_Framework_TestCase
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

    public function testProgressBar()
    {
        $this->command->setCode(function (InputInterface $input, OutputInterface $output) {
            $output = new SymfonyStyle($input, $output);

            $output->progressStart(10);
            $output->progressAdvance(4);
            $output->progressFinish();
        });

        $this->tester->execute(array(), array('interactive' => false, 'decorated' => false));

        if ('\\' !== DIRECTORY_SEPARATOR) {
            $this->assertStringEqualsFile(__DIR__.'/../Fixtures/Style/SymfonyStyle/progress-bar/output_linux.txt', $this->tester->getDisplay(true));
        } else {
            $this->assertStringEqualsFile(__DIR__.'/../Fixtures/Style/SymfonyStyle/progress-bar/output_windows.txt', $this->tester->getDisplay(true));
        }
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
}
