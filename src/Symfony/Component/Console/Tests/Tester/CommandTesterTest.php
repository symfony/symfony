<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Tester;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTesterTest extends \PHPUnit_Framework_TestCase
{
    protected $command;
    protected $tester;

    protected function setUp()
    {
        $this->command = new Command('foo');
        $this->command->addArgument('command');
        $this->command->addArgument('foo');
        $this->command->setCode(function ($input, $output) { $output->writeln('foo'); });

        $this->tester = new CommandTester($this->command);
        $this->tester->execute(array('foo' => 'bar'), array('interactive' => false, 'decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE));
    }

    protected function tearDown()
    {
        $this->command = null;
        $this->tester = null;
    }

    public function testExecute()
    {
        $this->assertFalse($this->tester->getInput()->isInteractive(), '->execute() takes an interactive option');
        $this->assertFalse($this->tester->getOutput()->isDecorated(), '->execute() takes a decorated option');
        $this->assertEquals(Output::VERBOSITY_VERBOSE, $this->tester->getOutput()->getVerbosity(), '->execute() takes a verbosity option');
    }

    public function testGetInput()
    {
        $this->assertEquals('bar', $this->tester->getInput()->getArgument('foo'), '->getInput() returns the current input instance');
    }

    public function testGetOutput()
    {
        rewind($this->tester->getOutput()->getStream());
        $this->assertEquals('foo'.PHP_EOL, stream_get_contents($this->tester->getOutput()->getStream()), '->getOutput() returns the current output instance');
    }

    public function testGetDisplay()
    {
        $this->assertEquals('foo'.PHP_EOL, $this->tester->getDisplay(), '->getDisplay() returns the display of the last execution');
    }
}
