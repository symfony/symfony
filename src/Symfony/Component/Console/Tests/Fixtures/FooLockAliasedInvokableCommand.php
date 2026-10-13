<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Lock\LockFactory;

#[AsCommand('foo:lock-aliased', null, ['foo:la'], true)]
class FooLockAliasedInvokableCommand
{
    use LockableTrait;

    public function __construct(LockFactory $lockFactory)
    {
        $this->lockFactory = $lockFactory;
    }

    public function __invoke(): int
    {
        if (!$this->lock()) {
            return Command::FAILURE;
        }

        $this->release();

        return Command::SUCCESS;
    }
}
