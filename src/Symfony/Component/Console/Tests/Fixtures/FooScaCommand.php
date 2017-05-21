<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class FooScaCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('foosca')
            ->setDescription('The foosca command');
        $this->addArgument(
            'items',
            InputArgument::IS_ARRAY,
            'Items to process'
        );
        $this->addOption(
            'bar',
            'b',
            InputOption::VALUE_NONE,
            'Enable barring'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bar = $input->getOption('bar');
        $output->writeln('<info>FooSca</info>' . ($bar ?  ' (barred)': ' (basic)'));

        foreach ($input->getArgument('items') as $item) {
            $output->writeln('Item: ' . $item);
        }
    }
}
