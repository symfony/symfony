<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;

#[AsCommand(name: 'foo:lock4')]
class FooLock4InvokableCommand
{
    use LockableTrait;

    public function __invoke(): int
    {
        if (!$this->lock()) {
            return Command::FAILURE;
        }

        $this->release();

        return Command::SUCCESS;
    }
}
