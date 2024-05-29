<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestAmbiguousCommandRegistering extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('test-ambiguous')
            ->setDescription('The test-ambiguous command')
            ->setAliases(['test'])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write('test-ambiguous');

        return 0;
    }
}
