<?php


use Symfony\Component\Console\Command\Command;

class Foo6Command extends Command
{
    protected function configure()
    {
        $this->setName('<fg=blue>foo:bar</fg=blue>');
    }

}