<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Console\Tests\Fixtures;

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Input\InputArgument;
use Symphony\Component\Console\Input\InputOption;

class DescriptorCommand2 extends Command
{
    protected function configure()
    {
        $this
            ->setName('descriptor:command2')
            ->setDescription('command 2 description')
            ->setHelp('command 2 help')
            ->addUsage('-o|--option_name <argument_name>')
            ->addUsage('<argument_name>')
            ->addArgument('argument_name', InputArgument::REQUIRED)
            ->addOption('option_name', 'o', InputOption::VALUE_NONE)
        ;
    }
}
