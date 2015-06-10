<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * TwigExtension configuration structure.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('twig');

        $rootNode
            ->children()
                ->scalarNode('exception_controller')->defaultValue('twig.controller.exception:showAction')->end()
            ->end()
        ;

        $this->addFormSection($rootNode);
        $this->addFormThemesSection($rootNode);
        $this->addGlobalsSection($rootNode);
        $this->addTwigOptions($rootNode);
        $this->addTwigFormatOptions($rootNode);

        return $treeBuilder;
    }

    private function addFormSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            // Check deprecation before the config is processed to ensure
            // the setting has been explicitly defined in a configuration file.
            ->beforeNormalization()
                ->ifTrue(function ($v) { return isset($v['form']['resources']); })
                ->then(function ($v) {
                    @trigger_error('The twig.form.resources configuration key is deprecated since version 2.6 and will be removed in 3.0. Use the twig.form_themes configuration key instead.', E_USER_DEPRECATED);

                    return $v;
                })
            ->end()
            ->validate()
                ->ifTrue(function ($v) {
                    return count($v['form']['resources']) > 0;
                })
                ->then(function ($v) {
                    $v['form_themes'] = array_values(array_unique(array_merge($v['form']['resources'], $v['form_themes'])));

                    return $v;
                })
            ->end()
            ->children()
                ->arrayNode('form')
                    ->info('Deprecated since version 2.6, to be removed in 3.0. Use twig.form_themes instead')
                    ->addDefaultsIfNotSet()
                    ->fixXmlConfig('resource')
                    ->children()
                        ->arrayNode('resources')
                            ->addDefaultChildrenIfNoneSet()
                            ->prototype('scalar')->defaultValue('form_div_layout.html.twig')->end()
                            ->example(array('MyBundle::form.html.twig'))
                            ->validate()
                                ->ifNotInArray(array('form_div_layout.html.twig'))
                                ->then(function ($v) {
                                    return array_merge(array('form_div_layout.html.twig'), $v);
                                })
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addFormThemesSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('form_theme')
            ->children()
                ->arrayNode('form_themes')
                    ->addDefaultChildrenIfNoneSet()
                    ->prototype('scalar')->defaultValue('form_div_layout.html.twig')->end()
                    ->example(array('MyBundle::form.html.twig'))
                    ->validate()
                        ->ifTrue(function ($v) { return !in_array('form_div_layout.html.twig', $v); })
                        ->then(function ($v) {
                            return array_merge(array('form_div_layout.html.twig'), $v);
                        })
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addGlobalsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('global')
            ->children()
                ->arrayNode('globals')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('key')
                    ->example(array('foo' => '"@bar"', 'pi' => 3.14))
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifTrue(function ($v) { return is_string($v) && 0 === strpos($v, '@'); })
                            ->then(function ($v) {
                                if (0 === strpos($v, '@@')) {
                                    return substr($v, 1);
                                }

                                return array('id' => substr($v, 1), 'type' => 'service');
                            })
                        ->end()
                        ->beforeNormalization()
                            ->ifTrue(function ($v) {
                                if (is_array($v)) {
                                    $keys = array_keys($v);
                                    sort($keys);

                                    return $keys !== array('id', 'type') && $keys !== array('value');
                                }

                                return true;
                            })
                            ->then(function ($v) { return array('value' => $v); })
                        ->end()
                        ->children()
                            ->scalarNode('id')->end()
                            ->scalarNode('type')
                                ->validate()
                                    ->ifNotInArray(array('service'))
                                    ->thenInvalid('The %s type is not supported')
                                ->end()
                            ->end()
                            ->variableNode('value')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addTwigOptions(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('path')
            ->children()
                ->variableNode('autoescape')->defaultValue('filename')->end()
                ->scalarNode('autoescape_service')->defaultNull()->end()
                ->scalarNode('autoescape_service_method')->defaultNull()->end()
                ->scalarNode('base_template_class')->example('Twig_Template')->cannotBeEmpty()->end()
                ->scalarNode('cache')->defaultValue('%kernel.cache_dir%/twig')->end()
                ->scalarNode('charset')->defaultValue('%kernel.charset%')->end()
                ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
                ->booleanNode('strict_variables')->end()
                ->scalarNode('auto_reload')->end()
                ->integerNode('optimizations')->min(-1)->end()
                ->arrayNode('paths')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('paths')
                    ->beforeNormalization()
                        ->always()
                        ->then(function ($paths) {
                            $normalized = array();
                            foreach ($paths as $path => $namespace) {
                                if (is_array($namespace)) {
                                    // xml
                                    $path = $namespace['value'];
                                    $namespace = $namespace['namespace'];
                                }

                                // path within the default namespace
                                if (ctype_digit((string) $path)) {
                                    $path = $namespace;
                                    $namespace = null;
                                }

                                $normalized[$path] = $namespace;
                            }

                            return $normalized;
                        })
                    ->end()
                    ->prototype('variable')->end()
                ->end()
            ->end()
        ;
    }

    private function addTwigFormatOptions(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('date')
                    ->info('The default format options used by the date filter')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('format')->defaultValue('F j, Y H:i')->end()
                        ->scalarNode('interval_format')->defaultValue('%d days')->end()
                        ->scalarNode('timezone')
                            ->info('The timezone used when formatting dates, when set to null, the timezone returned by date_default_timezone_get() is used')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('number_format')
                    ->info('The default format options for the number_format filter')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('decimals')->defaultValue(0)->end()
                        ->scalarNode('decimal_point')->defaultValue('.')->end()
                        ->scalarNode('thousands_separator')->defaultValue(',')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
