<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DependencyInjection\Provider;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;

/**
 * @author Victor Berchet <victor@suumit.com>
 */
class ApcProviderFactory extends AbstractProviderFactory
{
    public function getDefinition(array $config)
    {
        return new DefinitionDecorator('cache.provider.apc');
    }

    protected function getSignature(ContainerBuilder $container, array $config)
    {
        return $this->getName();
    }
}