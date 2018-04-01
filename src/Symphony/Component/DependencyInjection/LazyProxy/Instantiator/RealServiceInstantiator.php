<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\LazyProxy\Instantiator;

use Symphony\Component\DependencyInjection\ContainerInterface;
use Symphony\Component\DependencyInjection\Definition;

/**
 * {@inheritdoc}
 *
 * Noop proxy instantiator - simply produces the real service instead of a proxy instance.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class RealServiceInstantiator implements InstantiatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function instantiateProxy(ContainerInterface $container, Definition $definition, $id, $realInstantiator)
    {
        return call_user_func($realInstantiator);
    }
}
