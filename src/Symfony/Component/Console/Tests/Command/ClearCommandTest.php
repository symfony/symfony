<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class ClearCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecuteClearCommands()
    {
        $application = new Application();
        $commandTester = new CommandTester($command = $application->get('clear'));
        $commandTester->execute(array('command' => $command->getName()), array('decorated' => false));

        $this->assertSame(sprintf("\033\143"), $commandTester->getDisplay(), '->execute() Clears the terminal window');
    }
}
