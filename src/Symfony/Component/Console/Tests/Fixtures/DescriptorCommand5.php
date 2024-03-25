<?php

/*
 * This file is part of the Symfony package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class DescriptorCommand5 extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('descriptor:command5')
            ->setDescription('command 5 description')
            ->setHelp('command 5 help')
            ->addOption('deprecated_option', 'y', InputOption::DEPRECATED)
            ->addOption('hidden_option', 'z', InputOption::HIDDEN)
        ;
    }
}
