<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

class FooLock3Command extends Command
{
    use LockableTrait;

    public function __construct(LockFactory $lockFactory)
    {
        parent::__construct();

        $this->lockFactory = $lockFactory;
    }

    protected function configure(): void
    {
        $this->setName('foo:lock3');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            return 1;
        }

        $this->release();

        return 2;
    }
}
