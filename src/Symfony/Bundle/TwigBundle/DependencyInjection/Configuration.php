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
        $treeBuilder = new TreeBuilder('twig');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('exception_controller')
                    ->defaultValue(static function () {
                        @trigger_error('The "twig.exception_controller" configuration key has been deprecated in Symfony 4.4, set it to "null" and use "framework.error_controller" configuration key instead.', \E_USER_DEPRECATED);

                        return 'twig.controller.exception::showAction';
                    })
                    ->validate()
                        ->ifTrue(static function ($v) { return null !== $v; })
                        ->then(static function ($v) {
                            @trigger_error('The "twig.exception_controller" configuration key has been deprecated in Symfony 4.4, set it to "null" and use "framework.error_controller" configuration key instead.', \E_USER_DEPRECATED);

                            return $v;
                        })
                    ->end()
                ->end()
            ->end()
        ;

        $this->addFormThemesSection($rootNode);
        $this->addGlobalsSection($rootNode);
        $this->addTwigOptions($rootNode);
        $this->addTwigFormatOptions($rootNode);

        return $treeBuilder;
    }

    private function addFormThemesSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('form_theme')
            ->children()
                ->arrayNode('form_themes')
                    ->addDefaultChildrenIfNoneSet()
                    ->prototype('scalar')->defaultValue('form_div_layout.html.twig')->end()
                    ->example(['@My/form.html.twig'])
                    ->validate()
                        ->ifTrue(function ($v) { return !\in_array('form_div_layout.html.twig', $v); })
                        ->then(function ($v) {
                            return array_merge(['form_div_layout.html.twig'], $v);
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
                    ->example(['foo' => '@bar', 'pi' => 3.14])
                    ->prototype('array')
                        ->normalizeKeys(false)
                        ->beforeNormalization()
                            ->ifTrue(function ($v) { return \is_string($v) && str_starts_with($v, '@'); })
                            ->then(function ($v) {
                                if (str_starts_with($v, '@@')) {
                                    return substr($v, 1);
                                }

                                return ['id' => substr($v, 1), 'type' => 'service'];
                            })
                        ->end()
                        ->beforeNormalization()
                            ->ifTrue(function ($v) {
                                if (\is_array($v)) {
                                    $keys = array_keys($v);
                                    sort($keys);

                                    return $keys !== ['id', 'type'] && $keys !== ['value'];
                                }

                                return true;
                            })
                            ->then(function ($v) { return ['value' => $v]; })
                        ->end()
                        ->children()
                            ->scalarNode('id')->end()
                            ->scalarNode('type')
                                ->validate()
                                    ->ifNotInArray(['service'])
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
                ->variableNode('autoescape')->defaultValue('name')->end()
                ->scalarNode('autoescape_service')->defaultNull()->end()
                ->scalarNode('autoescape_service_method')->defaultNull()->end()
                ->scalarNode('base_template_class')->example('Twig\Template')->cannotBeEmpty()->end()
                ->scalarNode('cache')->defaultValue('%kernel.cache_dir%/twig')->end()
                ->scalarNode('charset')->defaultValue('%kernel.charset%')->end()
                ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
                ->booleanNode('strict_variables')
                    ->defaultValue(function () {
                        @trigger_error('Relying on the default value ("false") of the "twig.strict_variables" configuration option is deprecated since Symfony 4.1. You should use "%kernel.debug%" explicitly instead, which will be the new default in 5.0.', \E_USER_DEPRECATED);

                        return false;
                    })
                ->end()
                ->scalarNode('auto_reload')->end()
                ->integerNode('optimizations')->min(-1)->end()
                ->scalarNode('default_path')
                    ->info('The default path used to load templates')
                    ->defaultValue('%kernel.project_dir%/templates')
                ->end()
                ->arrayNode('paths')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('paths')
                    ->beforeNormalization()
                        ->always()
                        ->then(function ($paths) {
                            $normalized = [];
                            foreach ($paths as $path => $namespace) {
                                if (\is_array($namespace)) {
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
