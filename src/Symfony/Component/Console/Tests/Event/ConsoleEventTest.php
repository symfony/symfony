<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Event;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleEventTest extends \PHPUnit_Framework_TestCase
{
    /** @var Command */
    private $command;

    /** @var \PHPUnit_Framework_MockObject_MockObject|InputInterface */
    private $input;

    /** @var \PHPUnit_Framework_MockObject_MockObject|OutputInterface */
    private $output;

    public function setUp()
    {
        $this->command = new Command('my_mock_command');
        $this->input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $this->output = $this->getMock('Symfony\Component\Console\Output\OutputInterface');
    }

    public function testGetCommand()
    {
        $event = new ConsoleEvent($this->command, $this->input, $this->output);
        $this->assertSame($this->command, $event->getCommand());
    }

    public function testGetInput()
    {
        $event = new ConsoleEvent($this->command, $this->input, $this->output);
        $this->assertSame($this->input, $event->getInput());
    }

    public function testGetOutput()
    {
        $event = new ConsoleEvent($this->command, $this->input, $this->output);
        $this->assertSame($this->output, $event->getOutput());
    }
}
