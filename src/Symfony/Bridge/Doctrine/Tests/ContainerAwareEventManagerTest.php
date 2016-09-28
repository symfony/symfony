<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests;

use Symfony\Bridge\Doctrine\ContainerAwareEventManager;
use Symfony\Component\DependencyInjection\Container;

class ContainerAwareEventManagerTest extends LazyEventManagerTest
{
    protected function createEventManager($id = null, $listener = null)
    {
        $container = new Container();

        if ($id && $listener) {
            $container->set($id, $listener);
        }

        return new ContainerAwareEventManager($container);
    }
}
