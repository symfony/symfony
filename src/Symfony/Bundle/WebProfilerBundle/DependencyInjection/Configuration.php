<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the bundle.
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('web_profiler');

        $treeBuilder
            ->getRootNode()
            ->docUrl('https://symfony.com/doc/{version:major}.{version:minor}/reference/configuration/web_profiler.html', 'symfony/web-profiler-bundle')
            ->children()
                ->arrayNode('toolbar')
                    ->info('Profiler toolbar configuration')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('ajax_replace')
                            ->defaultFalse()
                            ->info('Replace toolbar on AJAX requests')
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('intercept_redirects')->defaultFalse()->end()
                ->scalarNode('excluded_ajax_paths')->defaultValue('^/((index|app(_[\w]+)?)\.php/)?_wdt')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
