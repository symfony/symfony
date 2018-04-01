<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symphony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\Command;

use Symphony\Component\Console\Command\Command;

class FooCommand extends Command
{
    protected function configure()
    {
        $this->setName('foo');
    }
}
