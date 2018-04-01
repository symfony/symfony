<?php

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Command\LockableTrait;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;

class FooLock2Command extends Command
{
    use LockableTrait;

    protected function configure()
    {
        $this->setName('foo:lock2');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->lock();
            $this->lock();
        } catch (LogicException $e) {
            return 1;
        }

        return 2;
    }
}
