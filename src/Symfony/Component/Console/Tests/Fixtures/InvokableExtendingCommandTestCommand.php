<?php

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand('invokable:test')]
class InvokableExtendingCommandTestCommand extends Command
{
    public function __invoke(): int
    {
        return Command::SUCCESS;
    }
}
