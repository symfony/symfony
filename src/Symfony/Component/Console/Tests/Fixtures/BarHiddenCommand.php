<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BarHiddenCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('bar:hidden')
            ->setAliases(['abarhidden'])
            ->setHidden(true)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}
