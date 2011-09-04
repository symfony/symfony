<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('monolog');

        $rootNode
            ->fixXmlConfig('handler')
            ->children()
                ->arrayNode('handlers')
                    ->canBeUnset()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->fixXmlConfig('member')
                        ->canBeUnset()
                        ->children()
                            ->scalarNode('type')
                                ->isRequired()
                                ->treatNullLike('null')
                                ->beforeNormalization()
                                    ->always()
                                    ->then(function($v) { return strtolower($v); })
                                ->end()
                            ->end()
                            ->scalarNode('id')->end()
                            ->scalarNode('priority')->defaultValue(0)->end()
                            ->scalarNode('level')->defaultValue('DEBUG')->end()
                            ->booleanNode('bubble')->defaultTrue()->end()
                            ->scalarNode('path')->defaultValue('%kernel.logs_dir%/%kernel.environment%.log')->end() // stream and rotating
                            ->scalarNode('ident')->defaultFalse()->end() // syslog
                            ->scalarNode('facility')->defaultValue('user')->end() // syslog
                            ->scalarNode('max_files')->defaultValue(0)->end() // rotating
                            ->scalarNode('action_level')->defaultValue('WARNING')->end() // fingers_crossed
                            ->booleanNode('stop_buffering')->defaultTrue()->end()// fingers_crossed
                            ->scalarNode('buffer_size')->defaultValue(0)->end() // fingers_crossed and buffer
                            ->scalarNode('handler')->end() // fingers_crossed and buffer
                            ->arrayNode('members') // group
                                ->canBeUnset()
                                ->performNoDeepMerging()
                                ->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('from_email')->end() // swift_mailer and native_mailer
                            ->scalarNode('to_email')->end() // swift_mailer and native_mailer
                            ->scalarNode('subject')->end() // swift_mailer and native_mailer
                            ->arrayNode('email_prototype') // swift_mailer
                                ->canBeUnset()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function($v) { return array('id' => $v); })
                                ->end()
                                ->children()
                                    ->scalarNode('id')->isRequired()->end()
                                    ->scalarNode('factory-method')->defaultNull()->end()
                                ->end()
                            ->end()
                            ->scalarNode('formatter')->end()
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return ('fingers_crossed' === $v['type'] || 'buffer' === $v['type']) && 1 !== count($v['handler']); })
                            ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler or BufferHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'swift_mailer' === $v['type'] && empty($v['email_prototype']) && (empty($v['from_email']) || empty($v['to_email']) || empty($v['subject'])); })
                            ->thenInvalid('The sender, recipient and subject or an email prototype have to be specified to use a SwiftMailerHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'native_mailer' === $v['type'] && (empty($v['from_email']) || empty($v['to_email']) || empty($v['subject'])); })
                            ->thenInvalid('The sender, recipient and subject have to be specified to use a NativeMailerHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'service' === $v['type'] && !isset($v['id']); })
                            ->thenInvalid('The id has to be specified to use a service as handler')
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(function($v) { return isset($v['debug']); })
                        ->thenInvalid('The "debug" name cannot be used as it is reserved for the handler of the profiler')
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
