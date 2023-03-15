<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FooSubnamespaced1Command extends Command
{
    public InputInterface $input;
    public OutputInterface $output;

    protected function configure(): void
    {
        $this
            ->setName('foo:bar:baz')
            ->setDescription('The foo:bar:baz command')
            ->setAliases(['foobarbaz'])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        return 0;
    }
}
