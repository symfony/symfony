<?php

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;

class TestTiti extends Command
{
    protected function configure()
    {
        $this
            ->setName('test-titi')
            ->setDescription('The test:titi command')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->write('test-titi');
    }
}
