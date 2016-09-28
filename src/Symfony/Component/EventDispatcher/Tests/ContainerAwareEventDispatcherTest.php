<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;

class ContainerAwareEventDispatcherTest extends LazyEventDispatcherTest
{
    protected function createEventDispatcher($id = null, $listener = null)
    {
        $container = new Container();

        $container->set($id, $listener);

        return new ContainerAwareEventDispatcher($container);
    }
}
