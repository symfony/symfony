<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Lock\LockFactory;

class FooLockMethodCommand
{
    use LockableTrait;

    public function __construct(LockFactory $lockFactory)
    {
        $this->lockFactory = $lockFactory;
    }

    #[AsCommand('foo:lock-method')]
    public function import(): int
    {
        if (!$this->lock()) {
            return Command::FAILURE;
        }

        $this->release();

        return Command::SUCCESS;
    }
}
