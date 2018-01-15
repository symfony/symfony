<?php

use Symfony\Component\Console\Command\Command;

class Foo8Command extends Command
{
    protected function configure()
    {
        $this->setName('foo:{bar}:{random}');
    }
}
