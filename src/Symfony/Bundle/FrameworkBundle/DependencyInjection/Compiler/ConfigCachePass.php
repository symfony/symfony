<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds services tagged config_cache.resource_checker to the config_cache_factory service, ordering them by priority.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 * @author Benjamin Klotz <bk@webfactory.de>
 */
class ConfigCachePass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container)
    {
        $resourceCheckers = $this->findAndSortTaggedServices('config_cache.resource_checker', $container);

        if (empty($resourceCheckers)) {
            return;
        }

        $container->getDefinition('config_cache_factory')->replaceArgument(0, $resourceCheckers);
    }
}
