<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lazily loads listeners from the dependency injection container.
 *
 * @author Jáchym Toušek <enumag@gmail.com>
 */
class ContainerAwareEventManager extends LazyEventManager
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct(function ($serviceId) use ($container) {
            return $container->get($serviceId);
        });
    }
}
