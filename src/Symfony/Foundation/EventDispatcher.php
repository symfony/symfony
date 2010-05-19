<?php

namespace Symfony\Foundation;

use Symfony\Components\EventDispatcher\EventDispatcher as BaseEventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\DependencyInjection\ContainerInterface;

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
 * @package    Symfony
 * @subpackage Foundation
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
        foreach ($container->findAnnotatedServiceIds('kernel.listener') as $id => $attributes) {
            $container->getService($id)->register($this);
        }
    }
}
