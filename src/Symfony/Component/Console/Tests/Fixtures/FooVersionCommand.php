<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FooVersionCommand extends Command
{
    public $input;
    public $output;

    protected function configure()
    {
        $this
            ->setName('foo:version')
            ->setDescription('The foo:version command')
            ->setAliases(['afooversion'])
            ->addOption('version', null, InputOption::VALUE_OPTIONAL, 'fooopt version')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $output->writeln('called');
        $output->writeln($this->input->getOption('version'));

        return 0;
    }
}
