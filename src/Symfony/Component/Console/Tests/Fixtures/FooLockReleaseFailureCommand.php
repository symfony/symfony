<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockInterface;

class FooLockReleaseFailureCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private LockInterface $failingLock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('foo:lock-release-failure');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // as if lock() had acquired a lock that the store then failed to release
        $this->lock = $this->failingLock;

        try {
            $this->release();
        } catch (LockReleasingException) {
        }

        if (!$this->lock()) {
            return 1;
        }

        $this->release();

        return 2;
    }
}
