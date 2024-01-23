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

use Symfony\Bridge\Twig\Extension\LogoutUrlExtension;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FirewallListenerFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\StatelessAuthenticatorFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Form\Extension\PasswordHasher\PasswordHasherExtension;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\AttributesRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\HostRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IpsRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PortRequestMatcher;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\SodiumPasswordHasher;
use Symfony\Component\Routing\Loader\ContainerLoader;
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\ConsensusStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\PriorityStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\ChainUserChecker;
use Symfony\Component\Security\Core\User\ChainUserProvider;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticatorManagerListener;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * SecurityExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SecurityExtension extends Extension implements PrependExtensionInterface
{
    private array $requestMatchers = [];
    private array $expressions = [];
    private array $contextListeners = [];
    /** @var list<array{int, AuthenticatorFactoryInterface}> */
    private array $factories = [];
    /** @var AuthenticatorFactoryInterface[] */
    private array $sortedFactories = [];
    private array $userProviderFactories = [];

    public function prepend(ContainerBuilder $container): void
    {
        foreach ($this->getSortedFactories() as $factory) {
            if ($factory instanceof PrependExtensionInterface) {
                $factory->prepend($container);
            }
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        if (!array_filter($configs)) {
            throw new InvalidConfigurationException(sprintf('Enabling bundle "%s" and not configuring it is not allowed.', SecurityBundle::class));
        }

        $mainConfig = $this->getConfiguration($configs, $container);

        $config = $this->processConfiguration($mainConfig, $configs);

        // load services
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));

        $loader->load('security.php');
        $loader->load('password_hasher.php');
        $loader->load('security_listeners.php');
        $loader->load('security_authenticator.php');
        $loader->load('security_authenticator_access_token.php');

        if ($container::willBeAvailable('symfony/twig-bridge', LogoutUrlExtension::class, ['symfony/security-bundle'])) {
            $loader->load('templating_twig.php');
        }

        $loader->load('collectors.php');

        if ($container->hasParameter('kernel.debug') && $container->getParameter('kernel.debug')) {
            $loader->load('security_debug.php');
        }

        if (!$container::willBeAvailable('symfony/expression-language', ExpressionLanguage::class, ['symfony/security-bundle'])) {
            $container->removeDefinition('security.expression_language');
            $container->removeDefinition('security.access.expression_voter');
            $container->removeDefinition('security.is_granted_attribute_expression_language');
        }

        if (!class_exists(PasswordHasherExtension::class)) {
            $container->removeDefinition('form.listener.password_hasher');
            $container->removeDefinition('form.type_extension.form.password_hasher');
            $container->removeDefinition('form.type_extension.password.password_hasher');
        }

        // set some global scalars
        $container->setParameter('security.access.denied_url', $config['access_denied_url']);
        $container->setParameter('security.authentication.manager.erase_credentials', $config['erase_credentials']);
        $container->setParameter('security.authentication.session_strategy.strategy', $config['session_fixation_strategy']);

        if (isset($config['access_decision_manager']['service'])) {
            $container->setAlias('security.access.decision_manager', $config['access_decision_manager']['service']);
        } elseif (isset($config['access_decision_manager']['strategy_service'])) {
            $container
                ->getDefinition('security.access.decision_manager')
                ->addArgument(new Reference($config['access_decision_manager']['strategy_service']));
        } else {
            $container
                ->getDefinition('security.access.decision_manager')
                ->addArgument($this->createStrategyDefinition(
                    $config['access_decision_manager']['strategy'] ?? MainConfiguration::STRATEGY_AFFIRMATIVE,
                    $config['access_decision_manager']['allow_if_all_abstain'],
                    $config['access_decision_manager']['allow_if_equal_granted_denied']
                ));
        }

        $container->setParameter('security.authentication.hide_user_not_found', $config['hide_user_not_found']);

        if (class_exists(Application::class)) {
            $loader->load('debug_console.php');
        }

        $this->createFirewalls($config, $container);

        if ($container::willBeAvailable('symfony/routing', ContainerLoader::class, ['symfony/security-bundle'])) {
            $this->createLogoutUrisParameter($config['firewalls'] ?? [], $container);
        } else {
            $container->removeDefinition('security.route_loader.logout');
        }

        $this->createAuthorization($config, $container);
        $this->createRoleHierarchy($config, $container);

        if ($config['password_hashers']) {
            $this->createHashers($config['password_hashers'], $container);
        }

        if (class_exists(Application::class)) {
            $loader->load('console.php');

            $container->getDefinition('security.command.user_password_hash')->replaceArgument(1, array_keys($config['password_hashers']));
        }

        $container->registerForAutoconfiguration(VoterInterface::class)
            ->addTag('security.voter');
    }

    private function createStrategyDefinition(string $strategy, bool $allowIfAllAbstainDecisions, bool $allowIfEqualGrantedDeniedDecisions): Definition
    {
        return match ($strategy) {
            MainConfiguration::STRATEGY_AFFIRMATIVE => new Definition(AffirmativeStrategy::class, [$allowIfAllAbstainDecisions]),
            MainConfiguration::STRATEGY_CONSENSUS => new Definition(ConsensusStrategy::class, [$allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions]),
            MainConfiguration::STRATEGY_UNANIMOUS => new Definition(UnanimousStrategy::class, [$allowIfAllAbstainDecisions]),
            MainConfiguration::STRATEGY_PRIORITY => new Definition(PriorityStrategy::class, [$allowIfAllAbstainDecisions]),
            default => throw new InvalidConfigurationException(sprintf('The strategy "%s" is not supported.', $strategy)),
        };
    }

    private function createRoleHierarchy(array $config, ContainerBuilder $container): void
    {
        if (!isset($config['role_hierarchy']) || 0 === \count($config['role_hierarchy'])) {
            $container->removeDefinition('security.access.role_hierarchy_voter');

            return;
        }

        $container->setParameter('security.role_hierarchy.roles', $config['role_hierarchy']);
        $container->removeDefinition('security.access.simple_role_voter');
    }

    private function createAuthorization(array $config, ContainerBuilder $container): void
    {
        foreach ($config['access_control'] as $access) {
            if (isset($access['request_matcher'])) {
                if ($access['path'] || $access['host'] || $access['port'] || $access['ips'] || $access['methods'] || $access['attributes'] || $access['route']) {
                    throw new InvalidConfigurationException('The "request_matcher" option should not be specified alongside other options. Consider integrating your constraints inside your RequestMatcher directly.');
                }
                $matcher = new Reference($access['request_matcher']);
            } else {
                $attributes = $access['attributes'];

                if ($access['route']) {
                    if (\array_key_exists('_route', $attributes)) {
                        throw new InvalidConfigurationException('The "route" option should not be specified alongside "attributes._route" option. Use just one of the options.');
                    }
                    $attributes['_route'] = $access['route'];
                }

                $matcher = $this->createRequestMatcher(
                    $container,
                    $access['path'],
                    $access['host'],
                    $access['port'],
                    $access['methods'],
                    $access['ips'],
                    $attributes
                );
            }

            $roles = $access['roles'];
            if ($access['allow_if']) {
                $roles[] = $this->createExpression($container, $access['allow_if']);
            }

            $emptyAccess = 0 === \count(array_filter($access));

            if ($emptyAccess) {
                throw new InvalidConfigurationException('One or more access control items are empty. Did you accidentally add lines only containing a "-" under "security.access_control"?');
            }

            $container->getDefinition('security.access_map')
                      ->addMethodCall('add', [$matcher, $roles, $access['requires_channel']]);
        }

        // allow cache warm-up for expressions
        if (\count($this->expressions)) {
            $container->getDefinition('security.cache_warmer.expression')
                ->replaceArgument(0, new IteratorArgument(array_values($this->expressions)));
        } else {
            $container->removeDefinition('security.cache_warmer.expression');
        }
    }

    private function createFirewalls(array $config, ContainerBuilder $container): void
    {
        if (!isset($config['firewalls'])) {
            return;
        }

        $firewalls = $config['firewalls'];
        $providerIds = $this->createUserProviders($config, $container);

        $container->setParameter('security.firewalls', array_keys($firewalls));

        // make the ContextListener aware of the configured user providers
        $contextListenerDefinition = $container->getDefinition('security.context_listener');
        $arguments = $contextListenerDefinition->getArguments();
        $userProviders = [];
        foreach ($providerIds as $userProviderId) {
            $userProviders[] = new Reference($userProviderId);
        }
        $arguments[1] = $userProviderIteratorsArgument = new IteratorArgument($userProviders);
        $contextListenerDefinition->setArguments($arguments);
        $nbUserProviders = \count($userProviders);

        if ($nbUserProviders > 1) {
            $container->setDefinition('security.user_providers', new Definition(ChainUserProvider::class, [$userProviderIteratorsArgument]));
        } elseif (0 === $nbUserProviders) {
            $container->removeDefinition('security.listener.user_provider');
        } else {
            $container->setAlias('security.user_providers', new Alias(current($providerIds)));
        }

        if (1 === \count($providerIds)) {
            $container->setAlias(UserProviderInterface::class, current($providerIds));
        }

        $customUserChecker = false;

        // load firewall map
        $mapDef = $container->getDefinition('security.firewall.map');
        $map = $authenticationProviders = $contextRefs = $authenticators = [];
        foreach ($firewalls as $name => $firewall) {
            if (isset($firewall['user_checker']) && 'security.user_checker' !== $firewall['user_checker']) {
                $customUserChecker = true;
            }

            $configId = 'security.firewall.map.config.'.$name;

            [$matcher, $listeners, $exceptionListener, $logoutListener, $firewallAuthenticators] = $this->createFirewall($container, $name, $firewall, $authenticationProviders, $providerIds, $configId);

            if (!$firewallAuthenticators) {
                $authenticators[$name] = null;
            } else {
                $firewallAuthenticatorRefs = [];
                foreach ($firewallAuthenticators as $authenticatorId) {
                    $firewallAuthenticatorRefs[$authenticatorId] = new Reference($authenticatorId);
                }
                $authenticators[$name] = ServiceLocatorTagPass::register($container, $firewallAuthenticatorRefs);
            }
            $contextId = 'security.firewall.map.context.'.$name;
            $isLazy = !$firewall['stateless'] && (!empty($firewall['anonymous']['lazy']) || $firewall['lazy']);
            $context = new ChildDefinition($isLazy ? 'security.firewall.lazy_context' : 'security.firewall.context');
            $context = $container->setDefinition($contextId, $context);
            $context
                ->replaceArgument(0, new IteratorArgument($listeners))
                ->replaceArgument(1, $exceptionListener)
                ->replaceArgument(2, $logoutListener)
                ->replaceArgument(3, new Reference($configId))
            ;

            $contextRefs[$contextId] = new Reference($contextId);
            $map[$contextId] = $matcher;
        }
        $container
            ->getDefinition('security.helper')
            ->replaceArgument(1, $authenticators)
        ;

        $container->setAlias('security.firewall.context_locator', (string) ServiceLocatorTagPass::register($container, $contextRefs));

        $mapDef->replaceArgument(0, new Reference('security.firewall.context_locator'));
        $mapDef->replaceArgument(1, new IteratorArgument($map));

        // register an autowire alias for the UserCheckerInterface if no custom user checker service is configured
        if (!$customUserChecker) {
            $container->setAlias(UserCheckerInterface::class, new Alias('security.user_checker', false));
        }
    }

    private function createFirewall(ContainerBuilder $container, string $id, array $firewall, array &$authenticationProviders, array $providerIds, string $configId): array
    {
        $config = $container->setDefinition($configId, new ChildDefinition('security.firewall.config'));
        $config->replaceArgument(0, $id);
        $config->replaceArgument(1, $firewall['user_checker']);

        // Matcher
        $matcher = null;
        if (isset($firewall['request_matcher'])) {
            $matcher = new Reference($firewall['request_matcher']);
        } elseif (isset($firewall['pattern']) || isset($firewall['host'])) {
            $pattern = $firewall['pattern'] ?? null;
            $host = $firewall['host'] ?? null;
            $methods = $firewall['methods'] ?? [];
            $matcher = $this->createRequestMatcher($container, $pattern, $host, null, $methods);
        }

        $config->replaceArgument(2, $matcher ? (string) $matcher : null);
        $config->replaceArgument(3, $firewall['security']);

        // Security disabled?
        if (false === $firewall['security']) {
            return [$matcher, [], null, null, []];
        }

        $config->replaceArgument(4, $firewall['stateless']);

        $firewallEventDispatcherId = 'security.event_dispatcher.'.$id;

        // Provider id (must be configured explicitly per firewall/authenticator if more than one provider is set)
        $defaultProvider = null;
        if (isset($firewall['provider'])) {
            if (!isset($providerIds[$normalizedName = str_replace('-', '_', $firewall['provider'])])) {
                throw new InvalidConfigurationException(sprintf('Invalid firewall "%s": user provider "%s" not found.', $id, $firewall['provider']));
            }
            $defaultProvider = $providerIds[$normalizedName];

            $container->setDefinition('security.listener.'.$id.'.user_provider', new ChildDefinition('security.listener.user_provider.abstract'))
                ->addTag('kernel.event_listener', ['dispatcher' => $firewallEventDispatcherId, 'event' => CheckPassportEvent::class, 'priority' => 2048, 'method' => 'checkPassport'])
                ->replaceArgument(0, new Reference($defaultProvider));
        } elseif (1 === \count($providerIds)) {
            $defaultProvider = reset($providerIds);
        }

        $config->replaceArgument(5, $defaultProvider);

        // Register Firewall-specific event dispatcher
        $container->register($firewallEventDispatcherId, EventDispatcher::class)
            ->addTag('event_dispatcher.dispatcher', ['name' => $firewallEventDispatcherId]);

        $eventDispatcherLocator = $container->getDefinition('security.firewall.event_dispatcher_locator');
        $eventDispatcherLocator
            ->replaceArgument(0, array_merge($eventDispatcherLocator->getArgument(0), [
                $id => new ServiceClosureArgument(new Reference($firewallEventDispatcherId)),
            ]))
        ;

        // Register Firewall-specific chained user checker
        $container->register('security.user_checker.chain.'.$id, ChainUserChecker::class)
            ->addArgument(new TaggedIteratorArgument('security.user_checker.'.$id));

        // Register listeners
        $listeners = [];
        $listenerKeys = [];

        // Channel listener
        $listeners[] = new Reference('security.channel_listener');

        $contextKey = null;
        // Context serializer listener
        if (false === $firewall['stateless']) {
            $contextKey = $firewall['context'] ?? $id;
            $listeners[] = new Reference($this->createContextListener($container, $contextKey, $firewallEventDispatcherId));
            $sessionStrategyId = 'security.authentication.session_strategy';

            $container
                ->setDefinition('security.listener.session.'.$id, new ChildDefinition('security.listener.session'))
                ->addTag('kernel.event_subscriber', ['dispatcher' => $firewallEventDispatcherId]);
        } else {
            $sessionStrategyId = 'security.authentication.session_strategy_noop';
        }
        $container->setAlias(new Alias('security.authentication.session_strategy.'.$id, false), $sessionStrategyId);

        $config->replaceArgument(6, $contextKey);

        // Logout listener
        $logoutListenerId = null;
        if (isset($firewall['logout'])) {
            $logoutListenerId = 'security.logout_listener.'.$id;
            $logoutListener = $container->setDefinition($logoutListenerId, new ChildDefinition('security.logout_listener'));
            $logoutListener->replaceArgument(2, new Reference($firewallEventDispatcherId));
            $logoutListener->replaceArgument(3, [
                'csrf_parameter' => $firewall['logout']['csrf_parameter'],
                'csrf_token_id' => $firewall['logout']['csrf_token_id'],
                'logout_path' => $firewall['logout']['path'],
            ]);

            $container->setDefinition('security.logout.listener.default.'.$id, new ChildDefinition('security.logout.listener.default'))
                ->replaceArgument(1, $firewall['logout']['target'])
                ->addTag('kernel.event_subscriber', ['dispatcher' => $firewallEventDispatcherId]);

            // add CSRF provider
            if ($firewall['logout']['enable_csrf']) {
                $logoutListener->addArgument(new Reference($firewall['logout']['csrf_token_manager']));
            }

            // add session logout listener
            if (true === $firewall['logout']['invalidate_session'] && false === $firewall['stateless']) {
                $container->setDefinition('security.logout.listener.session.'.$id, new ChildDefinition('security.logout.listener.session'))
                    ->addTag('kernel.event_subscriber', ['dispatcher' => $firewallEventDispatcherId]);
            }

            // add cookie logout listener
            if (\count($firewall['logout']['delete_cookies']) > 0) {
                $container->setDefinition('security.logout.listener.cookie_clearing.'.$id, new ChildDefinition('security.logout.listener.cookie_clearing'))
                    ->addArgument($firewall['logout']['delete_cookies'])
                    ->addTag('kernel.event_subscriber', ['dispatcher' => $firewallEventDispatcherId]);
            }

            // add clear site data listener
            if ($firewall['logout']['clear_site_data'] ?? false) {
                $container->setDefinition('security.logout.listener.clear_site_data.'.$id, new ChildDefinition('security.logout.listener.clear_site_data'))
                    ->addArgument($firewall['logout']['clear_site_data'])
                    ->addTag('kernel.event_subscriber', ['dispatcher' => $firewallEventDispatcherId]);
            }

            // register with LogoutUrlGenerator
            $container
                ->getDefinition('security.logout_url_generator')
                ->addMethodCall('registerListener', [
                    $id,
                    $firewall['logout']['path'],
                    $firewall['logout']['csrf_token_id'],
                    $firewall['logout']['csrf_parameter'],
                    isset($firewall['logout']['csrf_token_manager']) ? new Reference($firewall['logout']['csrf_token_manager']) : null,
                    false === $firewall['stateless'] && isset($firewall['context']) ? $firewall['context'] : null,
                ])
            ;

            $config->replaceArgument(12, $firewall['logout']);
        }

        // Determine default entry point
        $configuredEntryPoint = $firewall['entry_point'] ?? null;

        // Authentication listeners
        $firewallAuthenticationProviders = [];
        [$authListeners, $defaultEntryPoint] = $this->createAuthenticationListeners($container, $id, $firewall, $firewallAuthenticationProviders, $defaultProvider, $providerIds, $configuredEntryPoint);

        // $configuredEntryPoint is resolved into a service ID and stored in $defaultEntryPoint
        $configuredEntryPoint = $defaultEntryPoint;

        // authenticator manager
        $authenticators = array_map(fn ($id) => new Reference($id), $firewallAuthenticationProviders);
        $container
            ->setDefinition($managerId = 'security.authenticator.manager.'.$id, new ChildDefinition('security.authenticator.manager'))
            ->replaceArgument(0, $authenticators)
            ->replaceArgument(2, new Reference($firewallEventDispatcherId))
            ->replaceArgument(3, $id)
            ->replaceArgument(7, $firewall['required_badges'] ?? [])
            ->addTag('monolog.logger', ['channel' => 'security'])
        ;

        $managerLocator = $container->getDefinition('security.authenticator.managers_locator');
        $managerLocator->replaceArgument(0, array_merge($managerLocator->getArgument(0), [$id => new ServiceClosureArgument(new Reference($managerId))]));

        // authenticator manager listener
        $container
            ->setDefinition('security.firewall.authenticator.'.$id, new ChildDefinition('security.firewall.authenticator'))
            ->replaceArgument(0, new Reference($managerId))
        ;

        if ($container->hasDefinition('debug.security.firewall')) {
            $container
                ->register('debug.security.firewall.authenticator.'.$id, TraceableAuthenticatorManagerListener::class)
                ->setDecoratedService('security.firewall.authenticator.'.$id)
                ->setArguments([new Reference('debug.security.firewall.authenticator.'.$id.'.inner')])
                ->addTag('kernel.reset', ['method' => 'reset'])
            ;
        }

        // user checker listener
        $container
            ->setDefinition('security.listener.user_checker.'.$id, new ChildDefinition('security.listener.user_checker'))
            ->replaceArgument(0, new Reference('security.user_checker.'.$id))
            ->addTag('kernel.event_subscriber', ['dispatcher' => $firewallEventDispatcherId]);

        $listeners[] = new Reference('security.firewall.authenticator.'.$id);

        // Add authenticators to the debug:firewall command
        if ($container->hasDefinition('security.command.debug_firewall')) {
            $debugCommand = $container->getDefinition('security.command.debug_firewall');
            $debugCommand->replaceArgument(3, array_merge($debugCommand->getArgument(3), [$id => $authenticators]));
        }

        $config->replaceArgument(7, $configuredEntryPoint ?: $defaultEntryPoint);

        $listeners = array_merge($listeners, $authListeners);

        // Switch user listener
        if (isset($firewall['switch_user'])) {
            $listenerKeys[] = 'switch_user';
            $listeners[] = new Reference($this->createSwitchUserListener($container, $id, $firewall['switch_user'], $defaultProvider, $firewall['stateless']));
        }

        // Access listener
        $listeners[] = new Reference('security.access_listener');

        // Exception listener
        $exceptionListener = new Reference($this->createExceptionListener($container, $firewall, $id, $configuredEntryPoint ?: $defaultEntryPoint, $firewall['stateless']));

        $config->replaceArgument(8, $firewall['access_denied_handler'] ?? null);
        $config->replaceArgument(9, $firewall['access_denied_url'] ?? null);

        $container->setAlias('security.user_checker.'.$id, new Alias($firewall['user_checker'], false));

        foreach ($this->getSortedFactories() as $factory) {
            $key = str_replace('-', '_', $factory->getKey());
            if ('custom_authenticators' !== $key && \array_key_exists($key, $firewall)) {
                $listenerKeys[] = $key;
            }
        }

        if ($firewall['custom_authenticators'] ?? false) {
            foreach ($firewall['custom_authenticators'] as $customAuthenticatorId) {
                $listenerKeys[] = $customAuthenticatorId;
            }
        }

        $config->replaceArgument(10, $listenerKeys);
        $config->replaceArgument(11, $firewall['switch_user'] ?? null);

        return [$matcher, $listeners, $exceptionListener, null !== $logoutListenerId ? new Reference($logoutListenerId) : null, $firewallAuthenticationProviders];
    }

    private function createContextListener(ContainerBuilder $container, string $contextKey, ?string $firewallEventDispatcherId): string
    {
        if (isset($this->contextListeners[$contextKey])) {
            return $this->contextListeners[$contextKey];
        }

        $listenerId = 'security.context_listener.'.\count($this->contextListeners);
        $listener = $container->setDefinition($listenerId, new ChildDefinition('security.context_listener'));
        $listener->replaceArgument(2, $contextKey);
        if (null !== $firewallEventDispatcherId) {
            $listener->replaceArgument(4, new Reference($firewallEventDispatcherId));
            $listener->addTag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'method' => 'onKernelResponse']);
        }

        return $this->contextListeners[$contextKey] = $listenerId;
    }

    private function createAuthenticationListeners(ContainerBuilder $container, string $id, array $firewall, array &$authenticationProviders, ?string $defaultProvider, array $providerIds, ?string $defaultEntryPoint): array
    {
        $listeners = [];
        $entryPoints = [];

        foreach ($this->getSortedFactories() as $factory) {
            $key = str_replace('-', '_', $factory->getKey());

            if (isset($firewall[$key])) {
                $userProvider = $this->getUserProvider($container, $id, $firewall, $key, $defaultProvider, $providerIds);

                if (!$factory instanceof AuthenticatorFactoryInterface) {
                    throw new InvalidConfigurationException(sprintf('Authenticator factory "%s" ("%s") must implement "%s".', get_debug_type($factory), $key, AuthenticatorFactoryInterface::class));
                }

                if (null === $userProvider && !$factory instanceof StatelessAuthenticatorFactoryInterface) {
                    $userProvider = $this->createMissingUserProvider($container, $id, $key);
                }

                $authenticators = $factory->createAuthenticator($container, $id, $firewall[$key], $userProvider);
                if (\is_array($authenticators)) {
                    foreach ($authenticators as $authenticator) {
                        $authenticationProviders[] = $authenticator;
                        $entryPoints[] = $authenticator;
                    }
                } else {
                    $authenticationProviders[] = $authenticators;
                    $entryPoints[$key] = $authenticators;
                }

                if ($factory instanceof FirewallListenerFactoryInterface) {
                    $firewallListenerIds = $factory->createListeners($container, $id, $firewall[$key]);
                    foreach ($firewallListenerIds as $firewallListenerId) {
                        $listeners[] = new Reference($firewallListenerId);
                    }
                }
            }
        }

        // the actual entry point is configured by the RegisterEntryPointPass
        $container->setParameter('security.'.$id.'._indexed_authenticators', $entryPoints);

        return [$listeners, $defaultEntryPoint];
    }

    private function getUserProvider(ContainerBuilder $container, string $id, array $firewall, string $factoryKey, ?string $defaultProvider, array $providerIds): ?string
    {
        if (isset($firewall[$factoryKey]['provider'])) {
            if (!isset($providerIds[$normalizedName = str_replace('-', '_', $firewall[$factoryKey]['provider'])])) {
                throw new InvalidConfigurationException(sprintf('Invalid firewall "%s": user provider "%s" not found.', $id, $firewall[$factoryKey]['provider']));
            }

            return $providerIds[$normalizedName];
        }

        if ($defaultProvider) {
            return $defaultProvider;
        }

        if (!$providerIds) {
            if ($firewall['stateless'] ?? false) {
                return null;
            }

            return $this->createMissingUserProvider($container, $id, $factoryKey);
        }

        if ('remember_me' === $factoryKey || 'anonymous' === $factoryKey) {
            return 'security.user_providers';
        }

        throw new InvalidConfigurationException(sprintf('Not configuring explicitly the provider for the "%s" authenticator on "%s" firewall is ambiguous as there is more than one registered provider. Set the "provider" key to one of the configured providers, even if your custom authenticators don\'t use it.', $factoryKey, $id));
    }

    private function createMissingUserProvider(ContainerBuilder $container, string $id, string $factoryKey): string
    {
        $userProvider = sprintf('security.user.provider.missing.%s', $factoryKey);
        $container->setDefinition(
            $userProvider,
            (new ChildDefinition('security.user.provider.missing'))->replaceArgument(0, $id)
        );

        return $userProvider;
    }

    private function createHashers(array $hashers, ContainerBuilder $container): void
    {
        $hasherMap = [];
        foreach ($hashers as $class => $hasher) {
            $hasherMap[$class] = $this->createHasher($hasher);
        }

        $container
            ->getDefinition('security.password_hasher_factory')
            ->setArguments([$hasherMap])
        ;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return Reference|array<string, mixed>
     */
    private function createHasher(array $config): Reference|array
    {
        // a custom hasher service
        if (isset($config['id'])) {
            return $config['migrate_from'] ?? false ? [
                'instance' => new Reference($config['id']),
                'migrate_from' => $config['migrate_from'],
            ] : new Reference($config['id']);
        }

        if ($config['migrate_from'] ?? false) {
            return $config;
        }

        // plaintext hasher
        if ('plaintext' === $config['algorithm']) {
            $arguments = [$config['ignore_case']];

            return [
                'class' => PlaintextPasswordHasher::class,
                'arguments' => $arguments,
            ];
        }

        // pbkdf2 hasher
        if ('pbkdf2' === $config['algorithm']) {
            return [
                'class' => Pbkdf2PasswordHasher::class,
                'arguments' => [
                    $config['hash_algorithm'],
                    $config['encode_as_base64'],
                    $config['iterations'],
                    $config['key_length'],
                ],
            ];
        }

        // bcrypt hasher
        if ('bcrypt' === $config['algorithm']) {
            $config['algorithm'] = 'native';
            $config['native_algorithm'] = \PASSWORD_BCRYPT;

            return $this->createHasher($config);
        }

        // Argon2i hasher
        if ('argon2i' === $config['algorithm']) {
            if (SodiumPasswordHasher::isSupported() && !\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                $config['algorithm'] = 'sodium';
            } elseif (\defined('PASSWORD_ARGON2I')) {
                $config['algorithm'] = 'native';
                $config['native_algorithm'] = \PASSWORD_ARGON2I;
            } else {
                throw new InvalidConfigurationException(sprintf('Algorithm "argon2i" is not available. Either use "%s" or upgrade to PHP 7.2+ instead.', \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13') ? 'argon2id", "auto' : 'auto'));
            }

            return $this->createHasher($config);
        }

        if ('argon2id' === $config['algorithm']) {
            if (($hasSodium = SodiumPasswordHasher::isSupported()) && \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                $config['algorithm'] = 'sodium';
            } elseif (\defined('PASSWORD_ARGON2ID')) {
                $config['algorithm'] = 'native';
                $config['native_algorithm'] = \PASSWORD_ARGON2ID;
            } else {
                throw new InvalidConfigurationException(sprintf('Algorithm "argon2id" is not available. Either use "%s", upgrade to PHP 7.3+ or use libsodium 1.0.15+ instead.', \defined('PASSWORD_ARGON2I') || $hasSodium ? 'argon2i", "auto' : 'auto'));
            }

            return $this->createHasher($config);
        }

        if ('native' === $config['algorithm']) {
            return [
                'class' => NativePasswordHasher::class,
                'arguments' => [
                    $config['time_cost'],
                    (($config['memory_cost'] ?? 0) << 10) ?: null,
                    $config['cost'],
                ] + (isset($config['native_algorithm']) ? [3 => $config['native_algorithm']] : []),
            ];
        }

        if ('sodium' === $config['algorithm']) {
            if (!SodiumPasswordHasher::isSupported()) {
                throw new InvalidConfigurationException('Libsodium is not available. Install the sodium extension or use "auto" instead.');
            }

            return [
                'class' => SodiumPasswordHasher::class,
                'arguments' => [
                    $config['time_cost'],
                    (($config['memory_cost'] ?? 0) << 10) ?: null,
                ],
            ];
        }

        // run-time configured hasher
        return $config;
    }

    // Parses user providers and returns an array of their ids
    private function createUserProviders(array $config, ContainerBuilder $container): array
    {
        $providerIds = [];
        foreach ($config['providers'] as $name => $provider) {
            $id = $this->createUserDaoProvider($name, $provider, $container);
            $providerIds[str_replace('-', '_', $name)] = $id;
        }

        return $providerIds;
    }

    // Parses a <provider> tag and returns the id for the related user provider service
    private function createUserDaoProvider(string $name, array $provider, ContainerBuilder $container): string
    {
        $name = $this->getUserProviderId($name);

        // Doctrine Entity and In-memory DAO provider are managed by factories
        foreach ($this->userProviderFactories as $factory) {
            $key = str_replace('-', '_', $factory->getKey());

            if (!empty($provider[$key])) {
                $factory->create($container, $name, $provider[$key]);

                return $name;
            }
        }

        // Existing DAO service provider
        if (isset($provider['id'])) {
            $container->setAlias($name, new Alias($provider['id'], false));

            return $provider['id'];
        }

        // Chain provider
        if (isset($provider['chain'])) {
            $providers = [];
            foreach ($provider['chain']['providers'] as $providerName) {
                $providers[] = new Reference($this->getUserProviderId($providerName));
            }

            $container
                ->setDefinition($name, new ChildDefinition('security.user.provider.chain'))
                ->addArgument(new IteratorArgument($providers));

            return $name;
        }

        throw new InvalidConfigurationException(sprintf('Unable to create definition for "%s" user provider.', $name));
    }

    private function getUserProviderId(string $name): string
    {
        return 'security.user.provider.concrete.'.strtolower($name);
    }

    private function createExceptionListener(ContainerBuilder $container, array $config, string $id, ?string $defaultEntryPoint, bool $stateless): string
    {
        $exceptionListenerId = 'security.exception_listener.'.$id;
        $listener = $container->setDefinition($exceptionListenerId, new ChildDefinition('security.exception_listener'));
        $listener->replaceArgument(3, $id);
        $listener->replaceArgument(4, null === $defaultEntryPoint ? null : new Reference($defaultEntryPoint));
        $listener->replaceArgument(8, $stateless);

        // access denied handler setup
        if (isset($config['access_denied_handler'])) {
            $listener->replaceArgument(6, new Reference($config['access_denied_handler']));
        } elseif (isset($config['access_denied_url'])) {
            $listener->replaceArgument(5, $config['access_denied_url']);
        }

        return $exceptionListenerId;
    }

    private function createSwitchUserListener(ContainerBuilder $container, string $id, array $config, ?string $defaultProvider, bool $stateless): string
    {
        $userProvider = isset($config['provider']) ? $this->getUserProviderId($config['provider']) : $defaultProvider;

        if (!$userProvider) {
            throw new InvalidConfigurationException(sprintf('Not configuring explicitly the provider for the "switch_user" listener on "%s" firewall is ambiguous as there is more than one registered provider.', $id));
        }
        if ($stateless && null !== $config['target_route']) {
            throw new InvalidConfigurationException(sprintf('Cannot set a "target_route" for the "switch_user" listener on the "%s" firewall as it is stateless.', $id));
        }

        $switchUserListenerId = 'security.authentication.switchuser_listener.'.$id;
        $listener = $container->setDefinition($switchUserListenerId, new ChildDefinition('security.authentication.switchuser_listener'));
        $listener->replaceArgument(1, new Reference($userProvider));
        $listener->replaceArgument(2, new Reference('security.user_checker.'.$id));
        $listener->replaceArgument(3, $id);
        $listener->replaceArgument(6, $config['parameter']);
        $listener->replaceArgument(7, $config['role']);
        $listener->replaceArgument(9, $stateless);
        $listener->replaceArgument(11, $config['target_route']);

        return $switchUserListenerId;
    }

    private function createExpression(ContainerBuilder $container, string $expression): Reference
    {
        if (isset($this->expressions[$id = '.security.expression.'.ContainerBuilder::hash($expression)])) {
            return $this->expressions[$id];
        }

        if (!$container::willBeAvailable('symfony/expression-language', ExpressionLanguage::class, ['symfony/security-bundle'])) {
            throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
        }

        $container
            ->register($id, Expression::class)
            ->addArgument($expression)
        ;

        return $this->expressions[$id] = new Reference($id);
    }

    private function createRequestMatcher(ContainerBuilder $container, ?string $path = null, ?string $host = null, ?int $port = null, array $methods = [], ?array $ips = null, array $attributes = []): Reference
    {
        if ($methods) {
            $methods = array_map('strtoupper', $methods);
        }

        if ($ips) {
            foreach ($ips as $ip) {
                $container->resolveEnvPlaceholders($ip, null, $usedEnvs);

                if (!$usedEnvs && !$this->isValidIps($ip)) {
                    throw new \LogicException(sprintf('The given value "%s" in the "security.access_control" config option is not a valid IP address.', $ip));
                }

                $usedEnvs = null;
            }
        }

        $id = '.security.request_matcher.'.ContainerBuilder::hash([ChainRequestMatcher::class, $path, $host, $port, $methods, $ips, $attributes]);

        if (isset($this->requestMatchers[$id])) {
            return $this->requestMatchers[$id];
        }

        $arguments = [];
        if ($methods) {
            if (!$container->hasDefinition($lid = '.security.request_matcher.'.ContainerBuilder::hash([MethodRequestMatcher::class, $methods]))) {
                $container->register($lid, MethodRequestMatcher::class)->setArguments([$methods]);
            }
            $arguments[] = new Reference($lid);
        }

        if ($path) {
            if (!$container->hasDefinition($lid = '.security.request_matcher.'.ContainerBuilder::hash([PathRequestMatcher::class, $path]))) {
                $container->register($lid, PathRequestMatcher::class)->setArguments([$path]);
            }
            $arguments[] = new Reference($lid);
        }

        if ($host) {
            if (!$container->hasDefinition($lid = '.security.request_matcher.'.ContainerBuilder::hash([HostRequestMatcher::class, $host]))) {
                $container->register($lid, HostRequestMatcher::class)->setArguments([$host]);
            }
            $arguments[] = new Reference($lid);
        }

        if ($ips) {
            if (!$container->hasDefinition($lid = '.security.request_matcher.'.ContainerBuilder::hash([IpsRequestMatcher::class, $ips]))) {
                $container->register($lid, IpsRequestMatcher::class)->setArguments([$ips]);
            }
            $arguments[] = new Reference($lid);
        }

        if ($attributes) {
            if (!$container->hasDefinition($lid = '.security.request_matcher.'.ContainerBuilder::hash([AttributesRequestMatcher::class, $attributes]))) {
                $container->register($lid, AttributesRequestMatcher::class)->setArguments([$attributes]);
            }
            $arguments[] = new Reference($lid);
        }

        if ($port) {
            if (!$container->hasDefinition($lid = '.security.request_matcher.'.ContainerBuilder::hash([PortRequestMatcher::class, $port]))) {
                $container->register($lid, PortRequestMatcher::class)->setArguments([$port]);
            }
            $arguments[] = new Reference($lid);
        }

        $container
            ->register($id, ChainRequestMatcher::class)
            ->setArguments([$arguments])
        ;

        return $this->requestMatchers[$id] = new Reference($id);
    }

    public function addAuthenticatorFactory(AuthenticatorFactoryInterface $factory): void
    {
        $this->factories[] = [$factory->getPriority(), $factory];
        $this->sortedFactories = [];
    }

    public function addUserProviderFactory(UserProviderFactoryInterface $factory): void
    {
        $this->userProviderFactories[] = $factory;
    }

    public function getXsdValidationBasePath(): string|false
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace(): string
    {
        return 'http://symfony.com/schema/dic/security';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        // first assemble the factories
        return new MainConfiguration($this->getSortedFactories(), $this->userProviderFactories);
    }

    private function isValidIps(string|array $ips): bool
    {
        $ipsList = array_reduce((array) $ips, fn ($ips, $ip) => array_merge($ips, preg_split('/\s*,\s*/', $ip)), []);

        if (!$ipsList) {
            return false;
        }

        foreach ($ipsList as $cidr) {
            if (!$this->isValidIp($cidr)) {
                return false;
            }
        }

        return true;
    }

    private function isValidIp(string $cidr): bool
    {
        $cidrParts = explode('/', $cidr);

        if (1 === \count($cidrParts)) {
            return false !== filter_var($cidrParts[0], \FILTER_VALIDATE_IP);
        }

        $ip = $cidrParts[0];
        $netmask = $cidrParts[1];

        if (!ctype_digit($netmask)) {
            return false;
        }

        if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return $netmask <= 32;
        }

        if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            return $netmask <= 128;
        }

        return false;
    }

    /**
     * @return array<int, AuthenticatorFactoryInterface>
     */
    private function getSortedFactories(): array
    {
        if (!$this->sortedFactories) {
            $factories = [];
            foreach ($this->factories as $i => $factory) {
                $factories[] = array_merge($factory, [$i]);
            }

            usort($factories, fn ($a, $b) => $b[0] <=> $a[0] ?: $a[2] <=> $b[2]);

            $this->sortedFactories = array_column($factories, 1);
        }

        return $this->sortedFactories;
    }

    private function createLogoutUrisParameter(array $firewallsConfig, ContainerBuilder $container): void
    {
        $logoutUris = [];
        foreach ($firewallsConfig as $name => $config) {
            if (!$logoutPath = $config['logout']['path'] ?? null) {
                continue;
            }

            if ('/' === $logoutPath[0]) {
                $logoutUris[$name] = $logoutPath;
            }
        }

        $container->setParameter('security.logout_uris', $logoutUris);
    }
}
