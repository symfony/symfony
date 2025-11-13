<?php

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand('invokable:test', aliases: ['inv-test'], usages: ['usage1', 'usage2'], description: 'desc', help: 'help me')]
class InvokableTestCommand
{
    public function __invoke(): int
    {
        return Command::SUCCESS;
    }
}
