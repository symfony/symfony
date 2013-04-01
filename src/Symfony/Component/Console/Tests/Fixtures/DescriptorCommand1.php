<?php

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Command\Command;

class DescriptorCommand1 extends Command
{
    protected function configure()
    {
        $this
            ->setName('descriptor:command1')
            ->setAliases(array('alias1', 'alias2'))
            ->setDescription('command 1 description')
            ->setHelp('command 1 help')
        ;
    }
}
