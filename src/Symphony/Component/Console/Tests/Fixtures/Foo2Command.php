<?php

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;

class Foo2Command extends Command
{
    protected function configure()
    {
        $this
            ->setName('foo1:bar')
            ->setDescription('The foo1:bar command')
            ->setAliases(array('afoobar2'))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    }
}
