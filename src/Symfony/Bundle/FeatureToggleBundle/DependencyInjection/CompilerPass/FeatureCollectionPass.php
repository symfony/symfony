<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureToggleBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\FeatureToggle\Provider\ProviderInterface;

final class FeatureCollectionPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(ProviderInterface::class)->addTag('feature_toggle.feature_provider');

        $collection = $container->getDefinition('feature_toggle.feature_collection');

        foreach ($this->findAndSortTaggedServices('feature_toggle.feature_provider', $container) as $provider) {
            $collectionDefinition = (new Definition(\Closure::class))
                ->setFactory([\Closure::class, 'fromCallable'])
                ->setArguments([[$provider, 'provide']])
            ;

            $collection
                ->addMethodCall('withFeatures', [$collectionDefinition])
            ;
        }
    }
}
