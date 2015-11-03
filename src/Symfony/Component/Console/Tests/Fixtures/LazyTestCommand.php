<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Command\Command;

/**
 * @author Ivan Shcherbak <dev@funivan.com>
 */
class LazyTestCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $name = CustomCommandResolver::getNameFromClass(get_class($this));
        $this->setName($name);
    }
}
