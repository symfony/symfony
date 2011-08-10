<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the following tags:
 *
 *   * security.config
 *   * security.acl
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class MainConfiguration implements ConfigurationInterface
{
    private $factories;

    /**
     * Constructor.
     *
     * @param array $factories
     */
    public function __construct(array $factories)
    {
        $this->factories = $factories;
    }

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $tb = new TreeBuilder();
        $rootNode = $tb->root('security');

        $rootNode
            ->children()
                ->scalarNode('access_denied_url')->defaultNull()->end()
                ->scalarNode('session_fixation_strategy')->cannotBeEmpty()->defaultValue('migrate')->end()
                ->booleanNode('hide_user_not_found')->defaultTrue()->end()
                ->booleanNode('always_authenticate_before_granting')->defaultFalse()->end()
                ->arrayNode('access_decision_manager')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('strategy')->defaultValue('affirmative')->end()
                        ->booleanNode('allow_if_all_abstain')->defaultFalse()->end()
                        ->booleanNode('allow_if_equal_granted_denied')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end()
            // add a faux-entry for factories, so that no validation error is thrown
            ->fixXmlConfig('factory', 'factories')
            ->children()
                ->arrayNode('factories')->ignoreExtraKeys()->end()
            ->end()
        ;

        $this->addAclSection($rootNode);
        $this->addEncodersSection($rootNode);
        $this->addProvidersSection($rootNode);
        $this->addFirewallsSection($rootNode, $this->factories);
        $this->addAccessControlSection($rootNode);
        $this->addRoleHierarchySection($rootNode);

        return $tb;
    }

    private function addAclSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('acl')
                    ->children()
                        ->scalarNode('connection')->end()
                        ->arrayNode('cache')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('id')->end()
                                ->scalarNode('prefix')->defaultValue('sf2_acl_')->end()
                            ->end()
                        ->end()
                        ->scalarNode('provider')->end()
                        ->arrayNode('tables')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('class')->defaultValue('acl_classes')->end()
                                ->scalarNode('entry')->defaultValue('acl_entries')->end()
                                ->scalarNode('object_identity')->defaultValue('acl_object_identities')->end()
                                ->scalarNode('object_identity_ancestors')->defaultValue('acl_object_identity_ancestors')->end()
                                ->scalarNode('security_identity')->defaultValue('acl_security_identities')->end()
                            ->end()
                        ->end()
                        ->arrayNode('voter')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('allow_if_object_identity_unavailable')->defaultTrue()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addRoleHierarchySection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('role', 'role_hierarchy')
            ->children()
                ->arrayNode('role_hierarchy')
                    ->useAttributeAsKey('id')
                    ->prototype('array')
                        ->performNoDeepMerging()
                        ->beforeNormalization()->ifString()->then(function($v) { return array('value' => $v); })->end()
                        ->beforeNormalization()
                            ->ifTrue(function($v) { return is_array($v) && isset($v['value']); })
                            ->then(function($v) { return preg_split('/\s*,\s*/', $v['value']); })
                        ->end()
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addAccessControlSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('rule', 'access_control')
            ->children()
                ->arrayNode('access_control')
                    ->cannotBeOverwritten()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('requires_channel')->defaultNull()->end()
                            ->scalarNode('path')->defaultNull()->end()
                            ->scalarNode('host')->defaultNull()->end()
                            ->scalarNode('ip')->defaultNull()->end()
                            ->arrayNode('methods')
                                ->beforeNormalization()->ifString()->then(function($v) { return preg_split('/\s*,\s*/', $v); })->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                        ->fixXmlConfig('role')
                        ->children()
                            ->arrayNode('roles')
                                ->beforeNormalization()->ifString()->then(function($v) { return preg_split('/\s*,\s*/', $v); })->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addFirewallsSection(ArrayNodeDefinition $rootNode, array $factories)
    {
        $firewallNodeBuilder = $rootNode
            ->fixXmlConfig('firewall')
            ->children()
                ->arrayNode('firewalls')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->disallowNewKeysInSubsequentConfigs()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
        ;

        $firewallNodeBuilder
            ->scalarNode('pattern')->end()
            ->booleanNode('security')->defaultTrue()->end()
            ->scalarNode('request_matcher')->end()
            ->scalarNode('access_denied_url')->end()
            ->scalarNode('access_denied_handler')->end()
            ->scalarNode('entry_point')->end()
            ->scalarNode('provider')->end()
            ->booleanNode('stateless')->defaultFalse()->end()
            ->scalarNode('context')->cannotBeEmpty()->end()
            ->arrayNode('logout')
                ->treatTrueLike(array())
                ->canBeUnset()
                ->children()
                    ->scalarNode('path')->defaultValue('/logout')->end()
                    ->scalarNode('target')->defaultValue('/')->end()
                    ->scalarNode('success_handler')->end()
                    ->booleanNode('invalidate_session')->defaultTrue()->end()
                ->end()
                ->fixXmlConfig('delete_cookie')
                ->children()
                    ->arrayNode('delete_cookies')
                        ->beforeNormalization()
                            ->ifTrue(function($v) { return is_array($v) && is_int(key($v)); })
                            ->then(function($v) { return array_map(function($v) { return array('name' => $v); }, $v); })
                        ->end()
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->children()
                                ->scalarNode('path')->defaultNull()->end()
                                ->scalarNode('domain')->defaultNull()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->fixXmlConfig('handler')
                ->children()
                    ->arrayNode('handlers')
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('anonymous')
                ->canBeUnset()
                ->children()
                    ->scalarNode('key')->defaultValue(uniqid())->end()
                ->end()
            ->end()
            ->arrayNode('switch_user')
                ->canBeUnset()
                ->children()
                    ->scalarNode('provider')->end()
                    ->scalarNode('parameter')->defaultValue('_switch_user')->end()
                    ->scalarNode('role')->defaultValue('ROLE_ALLOWED_TO_SWITCH')->end()
                ->end()
            ->end()
        ;

        $abstractFactoryKeys = array();
        foreach ($factories as $factoriesAtPosition) {
            foreach ($factoriesAtPosition as $factory) {
                $name = str_replace('-', '_', $factory->getKey());
                $factoryNode = $firewallNodeBuilder->arrayNode($name)
                    ->canBeUnset()
                ;

                if ($factory instanceof AbstractFactory) {
                    $abstractFactoryKeys[] = $name;
                }

                $factory->addConfiguration($factoryNode);
            }
        }

        // check for unreachable check paths
        $firewallNodeBuilder
            ->end()
            ->validate()
                ->ifTrue(function($v) {
                    return true === $v['security'] && isset($v['pattern']) && !isset($v['request_matcher']);
                })
                ->then(function($firewall) use($abstractFactoryKeys) {
                    foreach ($abstractFactoryKeys as $k) {
                        if (!isset($firewall[$k]['check_path'])) {
                            continue;
                        }

                        if (!preg_match('#'.$firewall['pattern'].'#', $firewall[$k]['check_path'])) {
                            throw new \LogicException(sprintf('The check_path "%s" for login method "%s" is not matched by the firewall pattern "%s".', $firewall[$k]['check_path'], $k, $firewall['pattern']));
                        }
                    }

                    return $firewall;
                })
            ->end()
        ;
    }

    private function addProvidersSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('provider')
            ->children()
                ->arrayNode('providers')
                    ->disallowNewKeysInSubsequentConfigs()
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('id')->end()
                            ->arrayNode('entity')
                                ->children()
                                    ->scalarNode('class')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('property')->defaultNull()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->fixXmlConfig('provider')
                        ->children()
                            ->arrayNode('providers')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function($v) { return preg_split('/\s*,\s*/', $v); })
                                ->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                        ->fixXmlConfig('user')
                        ->children()
                            ->arrayNode('users')
                                ->useAttributeAsKey('name')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('password')->defaultValue(uniqid())->end()
                                        ->arrayNode('roles')
                                            ->beforeNormalization()->ifString()->then(function($v) { return preg_split('/\s*,\s*/', $v); })->end()
                                            ->prototype('scalar')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addEncodersSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('encoder')
            ->children()
                ->arrayNode('encoders')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('class')
                    ->prototype('array')
                        ->canBeUnset()
                        ->performNoDeepMerging()
                        ->beforeNormalization()->ifString()->then(function($v) { return array('algorithm' => $v); })->end()
                        ->children()
                            ->scalarNode('algorithm')->cannotBeEmpty()->end()
                            ->booleanNode('ignore_case')->defaultFalse()->end()
                            ->booleanNode('encode_as_base64')->defaultTrue()->end()
                            ->scalarNode('iterations')->defaultValue(5000)->end()
                            ->scalarNode('id')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
