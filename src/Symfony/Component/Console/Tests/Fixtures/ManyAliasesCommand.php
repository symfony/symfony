<?php

use Symfony\Component\Console\Command\Command;

class ManyAliasesCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('aliased')
            ->setAliases(['a', 'alias', 'alias2', 'alias3'])
            ->setDescription('Aliased command');
    }
}

class AlternativeCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('alternative')
            ->setDescription('Aliased command 2');
    }
}
