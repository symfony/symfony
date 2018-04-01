<?php

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Input\InputOption;
use Symphony\Component\Console\Output\OutputInterface;

class FooOptCommand extends Command
{
    public $input;
    public $output;

    protected function configure()
    {
        $this
            ->setName('foo:bar')
            ->setDescription('The foo:bar command')
            ->setAliases(array('afoobar'))
            ->addOption('fooopt', 'fo', InputOption::VALUE_OPTIONAL, 'fooopt description')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('interact called');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $output->writeln('called');
        $output->writeln($this->input->getOption('fooopt'));
    }
}
