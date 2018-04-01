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

class DescriptorCommand3 extends Command
{
    protected function configure()
    {
        $this
            ->setName('descriptor:command3')
            ->setDescription('command 3 description')
            ->setHelp('command 3 help')
            ->setHidden(true)
        ;
    }
}
