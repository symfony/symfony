<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
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

    private $factoryServiceId;
    private $resourceCheckerTag;

    public function __construct($factoryServiceId = 'config_cache_factory', $resourceCheckerTag = 'config_cache.resource_checker')
    {
        $this->factoryServiceId = $factoryServiceId;
        $this->resourceCheckerTag = $resourceCheckerTag;
    }

    public function process(ContainerBuilder $container)
    {
        $resourceCheckers = $this->findAndSortTaggedServices($this->resourceCheckerTag, $container);

        if (empty($resourceCheckers)) {
            return;
        }

        $container->getDefinition($this->factoryServiceId)->replaceArgument(0, new IteratorArgument($resourceCheckers));
    }
}
