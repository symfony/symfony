<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Descriptor\ApplicationDescription;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Tests\Fixtures\TestCommand;

class ApplicationDescriptorTest extends \PHPUnit_Framework_TestCase
{
    public function testGetMissingCommand()
    {
        $applicationDescriptor = new ApplicationDescription(new Application());

        $this->setExpectedException(CommandNotFoundException::class);
        $applicationDescriptor->getCommand('missing:command');
    }

    public function testInspectApplication()
    {
        $application = new Application();
        $testCommand = new TestCommand();
        $testCommand->setName(0);

        $application->addCommands([$testCommand]);
        $applicationDescriptor = new ApplicationDescription($application);

        $commands = $applicationDescriptor->getCommands();
        $this->assertArrayNotHasKey('namespace:name', $commands);
    }
}
