<?php

namespace Symfony\Bundle\SecurityBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Configuration\Builder\TreeBuilder;

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
class Configuration
{
    public function getAclConfigTree()
    {
        $tb = new TreeBuilder();

        return $tb
            ->root('security:acl', 'array')
                ->scalarNode('connection')->end()
                ->scalarNode('cache')->end()
            ->end()
            ->buildTree();
    }

    public function getFactoryConfigTree()
    {
        $tb = new TreeBuilder();

        return $tb
            ->root('security:config', 'array')
                ->fixXmlConfig('factory', 'factories')
                ->arrayNode('factories')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->buildTree();
    }

    public function getMainConfigTree(array $factories)
    {
        $tb = new TreeBuilder();
        $rootNode = $tb->root('security:config', 'array');

        $rootNode
            ->scalarNode('access_denied_url')->end()
            ->scalarNode('session_fixation_strategy')->cannotBeEmpty()->defaultValue('migrate')->end()
        ;

        $this->addEncodersSection($rootNode);
        $this->addProvidersSection($rootNode);
        $this->addFirewallsSection($rootNode, $factories);
        $this->addAccessControlSection($rootNode);
        $this->addRoleHierarchySection($rootNode);

        return $tb->buildTree();
    }

    protected function addRoleHierarchySection($rootNode)
    {
        $rootNode
            ->fixXmlConfig('role', 'role_hierarchy')
            ->arrayNode('role_hierarchy')
                ->containsNameValuePairsWithKeyAttribute('id')
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
        ;
    }

    protected function addAccessControlSection($rootNode)
    {
        $rootNode
            ->fixXmlConfig('rule', 'access_control')
            ->arrayNode('access_control')
                ->cannotBeOverwritten()
                ->prototype('array')
                    ->scalarNode('requires_channel')->defaultNull()->end()
                    ->scalarNode('path')->defaultNull()->end()
                    ->scalarNode('host')->defaultNull()->end()
                    ->scalarNode('ip')->defaultNull()->end()
                    ->arrayNode('methods')
                        ->beforeNormalization()->ifString()->then(function($v) { return preg_split('/\s*,\s*/', $v); })->end()
                        ->prototype('scalar')->end()
                    ->end()
                    ->fixXmlConfig('role')
                    ->arrayNode('roles')
                        ->beforeNormalization()->ifString()->then(function($v) { return preg_split('/\s*,\s*/', $v); })->end()
                        ->prototype('scalar')->end()
                    ->end()
                    ->fixXmlConfig('attribute')
                    ->arrayNode('attributes')
                        ->containsNameValuePairsWithKeyAttribute('key')
                        ->prototype('scalar')
                            ->beforeNormalization()
                                ->ifTrue(function($v) { return is_array($v) && isset($v['pattern']); })
                                ->then(function($v) { return $v['pattern']; })
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    protected function addFirewallsSection($rootNode, array $factories)
    {
        $firewallNodeBuilder =
        $rootNode
            ->fixXmlConfig('firewall')
            ->arrayNode('firewalls')
                ->disallowNewKeysInSubsequentConfigs()
                ->useAttributeAsKey('name')
                ->prototype('array')
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
                        ->scalarNode('path')->defaultValue('/logout')->end()
                        ->scalarNode('target')->defaultValue('/')->end()
                        ->booleanNode('invalidate_session')->defaultTrue()->end()
                        ->fixXmlConfig('delete_cookie')
                        ->arrayNode('delete_cookies')
                            ->beforeNormalization()
                                ->ifTrue(function($v) { return is_array($v) && is_int(key($v)); })
                                ->then(function($v) { return array_map(function($v) { return array('name' => $v); }, $v); })
                            ->end()
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->scalarNode('path')->defaultNull()->end()
                                ->scalarNode('domain')->defaultNull()->end()
                            ->end()
                        ->end()
                        ->fixXmlConfig('handler')
                        ->arrayNode('handlers')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                    ->booleanNode('anonymous')->end()
                    ->arrayNode('switch_user')
                        ->scalarNode('provider')->end()
                        ->scalarNode('parameter')->defaultValue('_switch_user')->end()
                        ->scalarNode('role')->defaultValue('ROLE_ALLOWED_TO_SWITCH')->end()
                    ->end()
        ;

        foreach ($factories as $factoriesAtPosition) {
            foreach ($factoriesAtPosition as $factory) {
                $factoryNode =
                $firewallNodeBuilder->arrayNode(str_replace('-', '_', $factory->getKey()))
                    ->canBeUnset()
                ;

                $factory->addConfiguration($factoryNode);
            }
        }
    }

    protected function addProvidersSection($rootNode)
    {
        $rootNode
            ->fixXmlConfig('provider')
            ->arrayNode('providers')
                ->disallowNewKeysInSubsequentConfigs()
                ->requiresAtLeastOneElement()
                ->useAttributeAsKey('name')
                ->prototype('array')
                    ->scalarNode('id')->end()
                    ->fixXmlConfig('provider')
                    ->arrayNode('providers')
                        ->prototype('scalar')->end()
                    ->end()
                    ->fixXmlConfig('user')
                    ->arrayNode('users')
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->scalarNode('password')->defaultValue(uniqid())->end()
                            ->arrayNode('roles')
                                ->beforeNormalization()->ifString()->then(function($v) { return preg_split('/\s*,\s*/', $v); })->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('entity')
                        ->scalarNode('class')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('property')->defaultNull()->end()
                    ->end()
                    ->arrayNode('document')
                        ->scalarNode('class')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('property')->defaultNull()->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    protected function addEncodersSection($rootNode)
    {
        $rootNode
            ->fixXmlConfig('encoder')
            ->arrayNode('encoders')
                ->useAttributeAsKey('class')
                ->prototype('array')
                    ->beforeNormalization()->ifString()->then(function($v) { return array('algorithm' => $v); })->end()
                    ->scalarNode('algorithm')->isRequired()->cannotBeEmpty()->end()
                    ->booleanNode('ignore_case')->end()
                    ->booleanNode('encode_as_base64')->end()
                    ->scalarNode('iterations')->end()
                    ->scalarNode('id')->end()
                ->end()
            ->end()
        ;
    }
}