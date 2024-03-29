<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Command\Command;

class Foo6Command extends Command
{
    protected function configure(): void
    {
        $this->setName('0foo:bar')->setDescription('0foo:bar command');
    }
}
