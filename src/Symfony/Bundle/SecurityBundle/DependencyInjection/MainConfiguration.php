<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AbstractFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;

/**
 * SecurityExtension configuration structure.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class MainConfiguration implements ConfigurationInterface
{
    /** @internal */
    public const STRATEGY_AFFIRMATIVE = 'affirmative';
    /** @internal */
    public const STRATEGY_CONSENSUS = 'consensus';
    /** @internal */
    public const STRATEGY_UNANIMOUS = 'unanimous';
    /** @internal */
    public const STRATEGY_PRIORITY = 'priority';

    private array $factories;
    private array $userProviderFactories;

    /**
     * @param array<AuthenticatorFactoryInterface> $factories
     */
    public function __construct(array $factories, array $userProviderFactories)
    {
        $this->factories = $factories;
        $this->userProviderFactories = $userProviderFactories;
    }

    /**
     * Generates the configuration tree builder.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('security');
        $rootNode = $tb->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('access_denied_url')->defaultNull()->example('/foo/error403')->end()
                ->enumNode('session_fixation_strategy')
                    ->values([SessionAuthenticationStrategy::NONE, SessionAuthenticationStrategy::MIGRATE, SessionAuthenticationStrategy::INVALIDATE])
                    ->defaultValue(SessionAuthenticationStrategy::MIGRATE)
                ->end()
                ->booleanNode('hide_user_not_found')->defaultTrue()->end()
                ->booleanNode('erase_credentials')->defaultTrue()->end()
                ->booleanNode('enable_authenticator_manager')->setDeprecated('symfony/security-bundle', '6.2', 'The "%node%" option at "%path%" is deprecated.')->defaultTrue()->end()
                ->arrayNode('access_decision_manager')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('strategy')
                            ->values($this->getAccessDecisionStrategies())
                        ->end()
                        ->scalarNode('service')->end()
                        ->scalarNode('strategy_service')->end()
                        ->booleanNode('allow_if_all_abstain')->defaultFalse()->end()
                        ->booleanNode('allow_if_equal_granted_denied')->defaultTrue()->end()
                    ->end()
                    ->validate()
                        ->ifTrue(fn ($v) => isset($v['strategy'], $v['service']))
                        ->thenInvalid('"strategy" and "service" cannot be used together.')
                    ->end()
                    ->validate()
                        ->ifTrue(fn ($v) => isset($v['strategy'], $v['strategy_service']))
                        ->thenInvalid('"strategy" and "strategy_service" cannot be used together.')
                    ->end()
                    ->validate()
                        ->ifTrue(fn ($v) => isset($v['service'], $v['strategy_service']))
                        ->thenInvalid('"service" and "strategy_service" cannot be used together.')
                    ->end()
                ->end()
            ->end()
        ;

        $this->addPasswordHashersSection($rootNode);
        $this->addProvidersSection($rootNode);
        $this->addFirewallsSection($rootNode, $this->factories);
        $this->addAccessControlSection($rootNode);
        $this->addRoleHierarchySection($rootNode);

        return $tb;
    }

    private function addRoleHierarchySection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->fixXmlConfig('role', 'role_hierarchy')
            ->children()
                ->arrayNode('role_hierarchy')
                    ->useAttributeAsKey('id')
                    ->prototype('array')
                        ->performNoDeepMerging()
                        ->beforeNormalization()->ifString()->then(fn ($v) => ['value' => $v])->end()
                        ->beforeNormalization()
                            ->ifTrue(fn ($v) => \is_array($v) && isset($v['value']))
                            ->then(fn ($v) => preg_split('/\s*,\s*/', $v['value']))
                        ->end()
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addAccessControlSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->fixXmlConfig('rule', 'access_control')
            ->children()
                ->arrayNode('access_control')
                    ->cannotBeOverwritten()
                    ->prototype('array')
                        ->fixXmlConfig('ip')
                        ->fixXmlConfig('method')
                        ->fixXmlConfig('attribute')
                        ->children()
                            ->scalarNode('request_matcher')->defaultNull()->end()
                            ->scalarNode('requires_channel')->defaultNull()->end()
                            ->scalarNode('path')
                                ->defaultNull()
                                ->info('use the urldecoded format')
                                ->example('^/path to resource/')
                            ->end()
                            ->scalarNode('host')->defaultNull()->end()
                            ->integerNode('port')->defaultNull()->end()
                            ->arrayNode('ips')
                                ->beforeNormalization()->ifString()->then(fn ($v) => [$v])->end()
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('attributes')
                                ->useAttributeAsKey('key')
                                ->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('route')->defaultNull()->end()
                            ->arrayNode('methods')
                                ->beforeNormalization()->ifString()->then(fn ($v) => preg_split('/\s*,\s*/', $v))->end()
                                ->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('allow_if')->defaultNull()->end()
                        ->end()
                        ->fixXmlConfig('role')
                        ->children()
                            ->arrayNode('roles')
                                ->beforeNormalization()->ifString()->then(fn ($v) => preg_split('/\s*,\s*/', $v))->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param array<AuthenticatorFactoryInterface> $factories
     */
    private function addFirewallsSection(ArrayNodeDefinition $rootNode, array $factories): void
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
                        ->fixXmlConfig('required_badge')
                        ->children()
        ;

        $firewallNodeBuilder
            ->scalarNode('pattern')
                ->beforeNormalization()
                    ->ifArray()
                    ->then(fn ($v) => sprintf('(?:%s)', implode('|', $v)))
                ->end()
            ->end()
            ->scalarNode('host')->end()
            ->arrayNode('methods')
                ->beforeNormalization()->ifString()->then(fn ($v) => preg_split('/\s*,\s*/', $v))->end()
                ->prototype('scalar')->end()
            ->end()
            ->booleanNode('security')->defaultTrue()->end()
            ->scalarNode('user_checker')
                ->defaultValue('security.user_checker')
                ->treatNullLike('security.user_checker')
                ->info('The UserChecker to use when authenticating users in this firewall.')
            ->end()
            ->scalarNode('request_matcher')->end()
            ->scalarNode('access_denied_url')->end()
            ->scalarNode('access_denied_handler')->end()
            ->scalarNode('entry_point')
                ->info(sprintf('An enabled authenticator name or a service id that implements "%s"', AuthenticationEntryPointInterface::class))
            ->end()
            ->scalarNode('provider')->end()
            ->booleanNode('stateless')->defaultFalse()->end()
            ->booleanNode('lazy')->defaultFalse()->end()
            ->scalarNode('context')->cannotBeEmpty()->end()
            ->arrayNode('logout')
                ->treatTrueLike([])
                ->canBeUnset()
                ->beforeNormalization()
                    ->ifTrue(fn ($v): bool => isset($v['csrf_token_generator']) && !isset($v['csrf_token_manager']))
                    ->then(function (array $v): array {
                        $v['csrf_token_manager'] = $v['csrf_token_generator'];

                        return $v;
                    })
                ->end()
                ->beforeNormalization()
                    ->ifTrue(fn ($v): bool => \is_array($v) && (isset($v['csrf_token_manager']) xor isset($v['enable_csrf'])))
                    ->then(function (array $v): array {
                        if (isset($v['csrf_token_manager'])) {
                            $v['enable_csrf'] = true;
                        } elseif ($v['enable_csrf']) {
                            $v['csrf_token_manager'] = 'security.csrf.token_manager';
                        }

                        return $v;
                    })
                ->end()
                ->children()
                    ->booleanNode('enable_csrf')->defaultNull()->end()
                    ->scalarNode('csrf_token_id')->defaultValue('logout')->end()
                    ->scalarNode('csrf_parameter')->defaultValue('_csrf_token')->end()
                    ->scalarNode('csrf_token_generator')
                        ->setDeprecated(
                            'symfony/security-bundle',
                            '6.3',
                            'The "%node%" option is deprecated. Use "csrf_token_manager" instead.'
                        )
                    ->end()
                    ->scalarNode('csrf_token_manager')->end()
                    ->scalarNode('path')->defaultValue('/logout')->end()
                    ->scalarNode('target')->defaultValue('/')->end()
                    ->booleanNode('invalidate_session')->defaultTrue()->end()
                    ->arrayNode('clear_site_data')
                        ->performNoDeepMerging()
                        ->beforeNormalization()->ifString()->then(fn ($v) => $v ? array_map('trim', explode(',', $v)) : [])->end()
                        ->enumPrototype()
                            ->values([
                                '*', 'cache', 'cookies', 'storage', 'executionContexts',
                            ])
                        ->end()
                    ->end()
                ->end()
                ->fixXmlConfig('delete_cookie')
                ->children()
                    ->arrayNode('delete_cookies')
                        ->normalizeKeys(false)
                        ->beforeNormalization()
                            ->ifTrue(fn ($v) => \is_array($v) && \is_int(key($v)))
                            ->then(fn ($v) => array_map(fn ($v) => ['name' => $v], $v))
                        ->end()
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->children()
                                ->scalarNode('path')->defaultNull()->end()
                                ->scalarNode('domain')->defaultNull()->end()
                                ->scalarNode('secure')->defaultFalse()->end()
                                ->scalarNode('samesite')->defaultNull()->end()
                                ->scalarNode('partitioned')->defaultFalse()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('switch_user')
                ->canBeUnset()
                ->children()
                    ->scalarNode('provider')->end()
                    ->scalarNode('parameter')->defaultValue('_switch_user')->end()
                    ->scalarNode('role')->defaultValue('ROLE_ALLOWED_TO_SWITCH')->end()
                    ->scalarNode('target_route')->defaultValue(null)->end()
                ->end()
            ->end()
            ->arrayNode('required_badges')
                ->info('A list of badges that must be present on the authenticated passport.')
                ->validate()
                    ->always()
                    ->then(function ($requiredBadges) {
                        return array_map(function ($requiredBadge) {
                            if (class_exists($requiredBadge)) {
                                return $requiredBadge;
                            }

                            if (!str_contains($requiredBadge, '\\')) {
                                $fqcn = 'Symfony\Component\Security\Http\Authenticator\Passport\Badge\\'.$requiredBadge;
                                if (class_exists($fqcn)) {
                                    return $fqcn;
                                }
                            }

                            throw new InvalidConfigurationException(sprintf('Undefined security Badge class "%s" set in "security.firewall.required_badges".', $requiredBadge));
                        }, $requiredBadges);
                    })
                ->end()
                ->prototype('scalar')->end()
            ->end()
        ;

        $abstractFactoryKeys = [];
        foreach ($factories as $factory) {
            $name = str_replace('-', '_', $factory->getKey());
            $factoryNode = $firewallNodeBuilder->arrayNode($name)
                ->canBeUnset()
            ;

            if ($factory instanceof AbstractFactory) {
                $abstractFactoryKeys[] = $name;
            }

            $factory->addConfiguration($factoryNode);
        }

        // check for unreachable check paths
        $firewallNodeBuilder
            ->end()
            ->validate()
                ->ifTrue(fn ($v) => true === $v['security'] && isset($v['pattern']) && !isset($v['request_matcher']))
                ->then(function ($firewall) use ($abstractFactoryKeys) {
                    foreach ($abstractFactoryKeys as $k) {
                        if (!isset($firewall[$k]['check_path'])) {
                            continue;
                        }

                        if (str_contains($firewall[$k]['check_path'], '/') && !preg_match('#'.$firewall['pattern'].'#', $firewall[$k]['check_path'])) {
                            throw new \LogicException(sprintf('The check_path "%s" for login method "%s" is not matched by the firewall pattern "%s".', $firewall[$k]['check_path'], $k, $firewall['pattern']));
                        }
                    }

                    return $firewall;
                })
            ->end()
        ;
    }

    private function addProvidersSection(ArrayNodeDefinition $rootNode): void
    {
        $providerNodeBuilder = $rootNode
            ->fixXmlConfig('provider')
            ->children()
                ->arrayNode('providers')
                    ->example([
                        'my_memory_provider' => [
                            'memory' => [
                                'users' => [
                                    'foo' => ['password' => 'foo', 'roles' => 'ROLE_USER'],
                                    'bar' => ['password' => 'bar', 'roles' => '[ROLE_USER, ROLE_ADMIN]'],
                                ],
                            ],
                        ],
                        'my_entity_provider' => ['entity' => ['class' => 'SecurityBundle:User', 'property' => 'username']],
                    ])
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
        ;

        $providerNodeBuilder
            ->children()
                ->scalarNode('id')->end()
                ->arrayNode('chain')
                    ->fixXmlConfig('provider')
                    ->children()
                        ->arrayNode('providers')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(fn ($v) => preg_split('/\s*,\s*/', $v))
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        foreach ($this->userProviderFactories as $factory) {
            $name = str_replace('-', '_', $factory->getKey());
            $factoryNode = $providerNodeBuilder->children()->arrayNode($name)->canBeUnset();

            $factory->addConfiguration($factoryNode);
        }

        $providerNodeBuilder
            ->validate()
                ->ifTrue(fn ($v) => \count($v) > 1)
                ->thenInvalid('You cannot set multiple provider types for the same provider')
            ->end()
            ->validate()
                ->ifTrue(fn ($v) => 0 === \count($v))
                ->thenInvalid('You must set a provider definition for the provider.')
            ->end()
        ;
    }

    private function addPasswordHashersSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->fixXmlConfig('password_hasher')
            ->children()
                ->arrayNode('password_hashers')
                    ->example([
                        'App\Entity\User1' => 'auto',
                        'App\Entity\User2' => [
                            'algorithm' => 'auto',
                            'time_cost' => 8,
                            'cost' => 13,
                        ],
                    ])
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('class')
                    ->prototype('array')
                        ->canBeUnset()
                        ->performNoDeepMerging()
                        ->beforeNormalization()->ifString()->then(fn ($v) => ['algorithm' => $v])->end()
                        ->children()
                            ->scalarNode('algorithm')
                                ->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(fn ($v) => !\is_string($v))
                                    ->thenInvalid('You must provide a string value.')
                                ->end()
                            ->end()
                            ->arrayNode('migrate_from')
                                ->prototype('scalar')->end()
                                ->beforeNormalization()->castToArray()->end()
                            ->end()
                            ->scalarNode('hash_algorithm')->info('Name of hashing algorithm for PBKDF2 (i.e. sha256, sha512, etc..) See hash_algos() for a list of supported algorithms.')->defaultValue('sha512')->end()
                            ->scalarNode('key_length')->defaultValue(40)->end()
                            ->booleanNode('ignore_case')->defaultFalse()->end()
                            ->booleanNode('encode_as_base64')->defaultTrue()->end()
                            ->scalarNode('iterations')->defaultValue(5000)->end()
                            ->integerNode('cost')
                                ->min(4)
                                ->max(31)
                                ->defaultNull()
                            ->end()
                            ->scalarNode('memory_cost')->defaultNull()->end()
                            ->scalarNode('time_cost')->defaultNull()->end()
                            ->scalarNode('id')->end()
                        ->end()
                    ->end()
                ->end()
        ->end();
    }

    private function getAccessDecisionStrategies(): array
    {
        return [
            self::STRATEGY_AFFIRMATIVE,
            self::STRATEGY_CONSENSUS,
            self::STRATEGY_UNANIMOUS,
            self::STRATEGY_PRIORITY,
        ];
    }
}
