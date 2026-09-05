<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandChain;
use Symfony\Component\Console\Input\ArrayInput;

class CommandChainTest extends TestCase
{
    public function testChainAccess()
    {
        $docker = new Command('docker');
        $up = new ChainTestUpCommand('docker:compose:up');
        $dockerInput = new ArrayInput([]);
        $upInput = new ArrayInput([]);

        $chain = new CommandChain([[$docker, $dockerInput], [$up, $upInput]]);

        $this->assertSame([$docker, $up], $chain->getCommands());
        $this->assertSame([$dockerInput, $upInput], $chain->getInputs());

        $this->assertSame($dockerInput, $chain->getInput('docker'));
        $this->assertSame($upInput, $chain->getInput('docker:compose:up'));
        $this->assertSame($upInput, $chain->getInput(ChainTestUpCommand::class));
        $this->assertNull($chain->getInput('nope'));

        $this->assertSame($docker, $chain->getCommand('docker'));
        $this->assertSame($up, $chain->getCommand(ChainTestUpCommand::class));
        $this->assertNull($chain->getCommand('nope'));
    }

    public function testClassLookupPrefersTheDeepestLevel()
    {
        $docker = new ChainTestUpCommand('docker');
        $up = new ChainTestUpCommand('docker:compose:up');

        $chain = new CommandChain([[$docker, new ArrayInput([])], [$up, new ArrayInput([])]]);

        $this->assertSame($up, $chain->getCommand(ChainTestUpCommand::class));
        $this->assertSame($up, $chain->getCommand(Command::class));
        $this->assertSame($docker, $chain->getCommand('docker'));
    }

    public function testLookupByInvokableClass()
    {
        $up = new Command('docker:compose:up', new ChainTestInvokable());
        $upInput = new ArrayInput([]);

        $chain = new CommandChain([[new Command('docker'), new ArrayInput([])], [$up, $upInput]]);

        $this->assertSame($up, $chain->getCommand(ChainTestInvokable::class));
        $this->assertSame($upInput, $chain->getInput(ChainTestInvokable::class));
    }

    public function testLookupByTheClassBehindAClosure()
    {
        $service = new ChainTestInvokable();
        $up = new Command('docker:compose:up')->setCode(\Closure::fromCallable($service));
        $down = new Command('docker:compose:down')->setCode(\Closure::fromCallable([$service, 'down']));
        $upInput = new ArrayInput([]);
        $downInput = new ArrayInput([]);

        $chain = new CommandChain([[new Command('docker'), new ArrayInput([])], [$up, $upInput], [$down, $downInput]]);

        $this->assertSame($down, $chain->getCommand(ChainTestInvokable::class));
        $this->assertSame($downInput, $chain->getInput(ChainTestInvokable::class));
        $this->assertSame($upInput, $chain->getInput('docker:compose:up'));
    }
}

class ChainTestUpCommand extends Command
{
}

#[AsCommand(name: 'docker:compose:up')]
class ChainTestInvokable
{
    public function __invoke(): int
    {
        return 0;
    }

    public function down(): int
    {
        return 0;
    }
}
