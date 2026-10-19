<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Lock\LockFactory;

#[AsCommand('foo:lock-methods')]
class FooLockMethodsCommand
{
    use LockableTrait;

    public function __construct(LockFactory $lockFactory)
    {
        $this->lockFactory = $lockFactory;
    }

    #[AsCommand('import')]
    public function import(): int
    {
        if (!$this->lock()) {
            return Command::FAILURE;
        }

        $this->release();

        return Command::SUCCESS;
    }

    #[AsCommand('export')]
    public function export(): int
    {
        if (!$this->lock()) {
            return Command::FAILURE;
        }

        $this->release();

        return Command::SUCCESS;
    }
}
