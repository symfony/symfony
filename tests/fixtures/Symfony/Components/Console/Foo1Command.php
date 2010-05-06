<?php

use Symfony\Components\Console\Command\Command;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;

class Foo1Command extends Command
{
    public $input;
    public $output;

    protected function configure()
    {
        $this
            ->setName('foo:bar1')
            ->setDescription('The foo:bar1 command')
            ->setAliases(array('afoobar1'))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }
}
