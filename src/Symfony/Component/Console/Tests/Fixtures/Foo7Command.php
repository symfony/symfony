<?php

use Symfony\Component\Console\Command\Command;

class Foo7Command extends Command
{
    protected function configure()
    {
        $this->setName('foo:{bar}');
    }
}
