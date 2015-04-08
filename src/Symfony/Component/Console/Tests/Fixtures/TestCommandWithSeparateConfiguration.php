<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommandWithSeparateConfiguration extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf('Hello, %s', $input->getArgument('name')));
        $output->writeln(sprintf('Command name: %s', $this->getName()));
        $output->writeln(sprintf('Description %s', $this->getDescription()));
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('interact called');
    }
}
