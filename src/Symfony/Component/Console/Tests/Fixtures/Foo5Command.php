<?php

use Symfony\Component\Console\Command\Command;

class Foo5Command extends Command
{
    protected function configure()
    {
        $this->setName('xxx:foo:bar');
    }
}
