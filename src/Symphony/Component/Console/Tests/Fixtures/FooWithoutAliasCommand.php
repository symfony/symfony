<?php

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Output\OutputInterface;

class FooWithoutAliasCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('foo')
            ->setDescription('The foo command')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('called');
    }
}
