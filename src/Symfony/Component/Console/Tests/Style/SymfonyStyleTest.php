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
        $baseDir = __DIR__.'/../Fixtures/Style/SymfonyStyle';

        return array_map(null, glob($baseDir.'/command/interactive_command_*.php'), glob($baseDir.'/output/interactive_output_*.txt'));
    }

    public function inputCommandToOutputFilesProvider()
    {
        $baseDir = __DIR__.'/../Fixtures/Style/SymfonyStyle';

        return array_map(null, glob($baseDir.'/command/command_*.php'), glob($baseDir.'/output/output_*.txt'));
    }
}
