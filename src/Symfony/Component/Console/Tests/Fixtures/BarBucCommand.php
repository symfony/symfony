<?php

use Symfony\Component\Console\Command\Command;

class BarBucCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('bar:buc');
    }
}
