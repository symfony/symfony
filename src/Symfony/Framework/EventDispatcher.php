<?php

namespace Symfony\Framework;

use Symfony\Component\EventDispatcher\EventDispatcher as BaseEventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This EventDispatcher implementation uses a DependencyInjection container to load listeners.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDispatcher extends BaseEventDispatcher
{
    /**
     * Constructor.
     *
     */
    public function __construct(ContainerInterface $container)
    {
        foreach ($container->findTaggedServiceIds('kernel.listener') as $id => $attributes) {
            $container->get($id)->register($this);
        }
    }
}
