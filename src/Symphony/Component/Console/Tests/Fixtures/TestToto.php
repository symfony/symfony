<?php

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;

class TestToto extends Command
{
    protected function configure()
    {
        $this
            ->setName('test-toto')
            ->setDescription('The test-toto command')
            ->setAliases(array('test'))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->write('test-toto');
    }
}
