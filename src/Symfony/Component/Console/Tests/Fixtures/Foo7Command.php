<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Foo7Command extends Command
{
    protected function configure()
    {
        $this
            ->setName('foo7:bar')
            ->addArgument('foo', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('foo argument: '.$input->getArgument('foo'));
    }
}
