<?php

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Command\LockableTrait;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;

class FooLockCommand extends Command
{
    use LockableTrait;

    protected function configure()
    {
        $this->setName('foo:lock');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            return 1;
        }

        $this->release();

        return 2;
    }
}
