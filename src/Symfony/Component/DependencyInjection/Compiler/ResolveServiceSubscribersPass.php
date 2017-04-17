<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to inject their service locator to service subscribers.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResolveServiceSubscribersPass extends AbstractRecursivePass
{
    private $serviceLocator;

    protected function processValue($value, $isRoot = false)
    {
        if ($value instanceof Reference && $this->serviceLocator && ContainerInterface::class === (string) $value) {
            return new Reference($this->serviceLocator);
        }

        if (!$value instanceof Definition) {
            return parent::processValue($value, $isRoot);
        }

        $serviceLocator = $this->serviceLocator;
        $this->serviceLocator = $value->hasTag('container.service_subscriber.locator') ? $value->getTag('container.service_subscriber.locator')[0]['id'] : null;

        try {
            return parent::processValue($value);
        } finally {
            $this->serviceLocator = $serviceLocator;
        }
    }
}
