<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FooCommand extends Command
{
    public InputInterface $input;
    public OutputInterface $output;

    protected function configure(): void
    {
        $this
            ->setName('foo:bar')
            ->setDescription('The foo:bar command')
            ->setAliases(['afoobar'])
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('interact called');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $output->writeln('called');

        return 0;
    }
}
