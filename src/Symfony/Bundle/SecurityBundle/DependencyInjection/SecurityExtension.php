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

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Encoder\Argon2iPasswordEncoder;
use Symfony\Component\Templating\Helper\Helper;
use Twig\Extension\AbstractExtension;

/**
 * SecurityExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SecurityExtension extends Extension
{
    private $requestMatchers = [];
    private $expressions = [];
    private $contextListeners = [];
    private $listenerPositions = ['pre_auth', 'form', 'http', 'remember_me'];
    private $factories = [];
    private $userProviderFactories = [];
    private $expressionLanguage;
    private $logoutOnUserChangeByContextKey = [];
    private $statelessFirewallKeys = [];

    public function __construct()
    {
        foreach ($this->listenerPositions as $position) {
            $this->factories[$position] = [];
        }
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        if (!array_filter($configs)) {
            return;
        }

        $mainConfig = $this->getConfiguration($configs, $container);

        $config = $this->processConfiguration($mainConfig, $configs);

        // load services
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('security.xml');
        $loader->load('security_listeners.xml');
        $loader->load('security_rememberme.xml');

        if (class_exists(Helper::class)) {
            $loader->load('templating_php.xml');

            $container->getDefinition('templating.helper.logout_url')->setPrivate(true);
            $container->getDefinition('templating.helper.security')->setPrivate(true);
        }

        if (class_exists(AbstractExtension::class)) {
            $loader->load('templating_twig.xml');
        }

        $loader->load('collectors.xml');
        $loader->load('guard.xml');

        $container->getDefinition('security.authentication.guard_handler')->setPrivate(true);
        $container->getDefinition('security.firewall')->setPrivate(true);
        $container->getDefinition('security.firewall.context')->setPrivate(true);
        $container->getDefinition('security.validator.user_password')->setPrivate(true);
        $container->getDefinition('security.rememberme.response_listener')->setPrivate(true);
        $container->getAlias('security.encoder_factory')->setPrivate(true);

        if ($container->hasParameter('kernel.debug') && $container->getParameter('kernel.debug')) {
            $loader->load('security_debug.xml');

            $container->getAlias('security.firewall')->setPrivate(true);
        }

        if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
            $container->removeDefinition('security.expression_language');
            $container->removeDefinition('security.access.expression_voter');
        }

        // set some global scalars
        $container->setParameter('security.access.denied_url', $config['access_denied_url']);
        $container->setParameter('security.authentication.manager.erase_credentials', $config['erase_credentials']);
        $container->setParameter('security.authentication.session_strategy.strategy', $config['session_fixation_strategy']);

        if (isset($config['access_decision_manager']['service'])) {
            $container->setAlias('security.access.decision_manager', $config['access_decision_manager']['service'])->setPrivate(true);
        } else {
            $container
                ->getDefinition('security.access.decision_manager')
                ->addArgument($config['access_decision_manager']['strategy'])
                ->addArgument($config['access_decision_manager']['allow_if_all_abstain'])
                ->addArgument($config['access_decision_manager']['allow_if_equal_granted_denied']);
        }

        $container->setParameter('security.access.always_authenticate_before_granting', $config['always_authenticate_before_granting']);
        $container->setParameter('security.authentication.hide_user_not_found', $config['hide_user_not_found']);

        $this->createFirewalls($config, $container);
        $this->createAuthorization($config, $container);
        $this->createRoleHierarchy($config, $container);

        $container->getDefinition('security.authentication.guard_handler')
            ->replaceArgument(2, $this->statelessFirewallKeys);

        if ($config['encoders']) {
            $this->createEncoders($config['encoders'], $container);
        }

        if (class_exists(Application::class)) {
            $loader->load('console.xml');
            $container->getDefinition('security.command.user_password_encoder')->replaceArgument(1, array_keys($config['encoders']));
        }

        // load ACL
        if (isset($config['acl'])) {
            $this->aclLoad($config['acl'], $container);
        } else {
            $container->removeDefinition('security.command.init_acl');
            $container->removeDefinition('security.command.set_acl');
        }

        $container->registerForAutoconfiguration(VoterInterface::class)
            ->addTag('security.voter');

        if (\PHP_VERSION_ID < 70000) {
            // add some required classes for compilation
            $this->addClassesToCompile([
                'Symfony\Component\Security\Http\Firewall',
                'Symfony\Component\Security\Core\User\UserProviderInterface',
                'Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager',
                'Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage',
                'Symfony\Component\Security\Core\Authorization\AccessDecisionManager',
                'Symfony\Component\Security\Core\Authorization\AuthorizationChecker',
                'Symfony\Component\Security\Core\Authorization\Voter\VoterInterface',
                'Symfony\Bundle\SecurityBundle\Security\FirewallConfig',
                'Symfony\Bundle\SecurityBundle\Security\FirewallContext',
                'Symfony\Component\HttpFoundation\RequestMatcher',
            ]);
        }
    }

    private function aclLoad($config, ContainerBuilder $container)
    {
        if (!interface_exists('Symfony\Component\Security\Acl\Model\AclInterface')) {
            throw new \LogicException('You must install symfony/security-acl in order to use the ACL functionality.');
        }

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('security_acl.xml');

        if (isset($config['cache']['id'])) {
            $container->setAlias('security.acl.cache', $config['cache']['id'])->setPrivate(true);
        }
        $container->getDefinition('security.acl.voter.basic_permissions')->addArgument($config['voter']['allow_if_object_identity_unavailable']);

        // custom ACL provider
        if (isset($config['provider'])) {
            $container->setAlias('security.acl.provider', $config['provider'])->setPrivate(true);

            return;
        }

        $this->configureDbalAclProvider($config, $container, $loader);
    }

    private function configureDbalAclProvider(array $config, ContainerBuilder $container, $loader)
    {
        $loader->load('security_acl_dbal.xml');

        $container->getDefinition('security.acl.dbal.schema')->setPrivate(true);
        $container->getAlias('security.acl.dbal.connection')->setPrivate(true);
        $container->getAlias('security.acl.provider')->setPrivate(true);

        if (null !== $config['connection']) {
            $container->setAlias('security.acl.dbal.connection', sprintf('doctrine.dbal.%s_connection', $config['connection']))->setPrivate(true);
        }

        $container
            ->getDefinition('security.acl.dbal.schema_listener')
            ->addTag('doctrine.event_listener', [
                'connection' => $config['connection'],
                'event' => 'postGenerateSchema',
                'lazy' => true,
            ])
        ;

        $container->getDefinition('security.acl.cache.doctrine')->addArgument($config['cache']['prefix']);

        $container->setParameter('security.acl.dbal.class_table_name', $config['tables']['class']);
        $container->setParameter('security.acl.dbal.entry_table_name', $config['tables']['entry']);
        $container->setParameter('security.acl.dbal.oid_table_name', $config['tables']['object_identity']);
        $container->setParameter('security.acl.dbal.oid_ancestors_table_name', $config['tables']['object_identity_ancestors']);
        $container->setParameter('security.acl.dbal.sid_table_name', $config['tables']['security_identity']);
    }

    private function createRoleHierarchy(array $config, ContainerBuilder $container)
    {
        if (!isset($config['role_hierarchy']) || 0 === \count($config['role_hierarchy'])) {
            $container->removeDefinition('security.access.role_hierarchy_voter');

            return;
        }

        $container->setParameter('security.role_hierarchy.roles', $config['role_hierarchy']);
        $container->removeDefinition('security.access.simple_role_voter');
    }

    private function createAuthorization($config, ContainerBuilder $container)
    {
        if (!$config['access_control']) {
            return;
        }

        if (\PHP_VERSION_ID < 70000) {
            $this->addClassesToCompile([
                'Symfony\\Component\\Security\\Http\\AccessMap',
            ]);
        }

        foreach ($config['access_control'] as $access) {
            $matcher = $this->createRequestMatcher(
                $container,
                $access['path'],
                $access['host'],
                $access['methods'],
                $access['ips']
            );

            $attributes = $access['roles'];
            if ($access['allow_if']) {
                $attributes[] = $this->createExpression($container, $access['allow_if']);
            }

            $container->getDefinition('security.access_map')
                      ->addMethodCall('add', [$matcher, $attributes, $access['requires_channel']]);
        }
    }

    private function createFirewalls($config, ContainerBuilder $container)
    {
        if (!isset($config['firewalls'])) {
            return;
        }

        $firewalls = $config['firewalls'];
        $providerIds = $this->createUserProviders($config, $container);

        // make the ContextListener aware of the configured user providers
        $contextListenerDefinition = $container->getDefinition('security.context_listener');
        $arguments = $contextListenerDefinition->getArguments();
        $userProviders = [];
        foreach ($providerIds as $userProviderId) {
            $userProviders[] = new Reference($userProviderId);
        }
        $arguments[1] = new IteratorArgument($userProviders);
        $contextListenerDefinition->setArguments($arguments);

        $customUserChecker = false;

        // load firewall map
        $mapDef = $container->getDefinition('security.firewall.map');
        $map = $authenticationProviders = $contextRefs = [];
        foreach ($firewalls as $name => $firewall) {
            if (isset($firewall['user_checker']) && 'security.user_checker' !== $firewall['user_checker']) {
                $customUserChecker = true;
            }

            $configId = 'security.firewall.map.config.'.$name;

            list($matcher, $listeners, $exceptionListener, $logoutListener) = $this->createFirewall($container, $name, $firewall, $authenticationProviders, $providerIds, $configId);

            $contextId = 'security.firewall.map.context.'.$name;
            $context = $container->setDefinition($contextId, new ChildDefinition('security.firewall.context'));
            $context
                ->replaceArgument(0, new IteratorArgument($listeners))
                ->replaceArgument(1, $exceptionListener)
                ->replaceArgument(2, $logoutListener)
                ->replaceArgument(3, new Reference($configId))
            ;

            $contextRefs[$contextId] = new Reference($contextId);
            $map[$contextId] = $matcher;
        }
        $mapDef->replaceArgument(0, ServiceLocatorTagPass::register($container, $contextRefs));
        $mapDef->replaceArgument(1, new IteratorArgument($map));

        // add authentication providers to authentication manager
        $authenticationProviders = array_map(function ($id) {
            return new Reference($id);
        }, array_values(array_unique($authenticationProviders)));
        $container
            ->getDefinition('security.authentication.manager')
            ->replaceArgument(0, new IteratorArgument($authenticationProviders))
        ;

        // register an autowire alias for the UserCheckerInterface if no custom user checker service is configured
        if (!$customUserChecker) {
            $container->setAlias('Symfony\Component\Security\Core\User\UserCheckerInterface', new Alias('security.user_checker', false));
        }
    }

    private function createFirewall(ContainerBuilder $container, $id, $firewall, &$authenticationProviders, $providerIds, $configId)
    {
        $config = $container->setDefinition($configId, new ChildDefinition('security.firewall.config'));
        $config->replaceArgument(0, $id);
        $config->replaceArgument(1, $firewall['user_checker']);

        // Matcher
        $matcher = null;
        if (isset($firewall['request_matcher'])) {
            $matcher = new Reference($firewall['request_matcher']);
        } elseif (isset($firewall['pattern']) || isset($firewall['host'])) {
            $pattern = isset($firewall['pattern']) ? $firewall['pattern'] : null;
            $host = isset($firewall['host']) ? $firewall['host'] : null;
            $methods = isset($firewall['methods']) ? $firewall['methods'] : [];
            $matcher = $this->createRequestMatcher($container, $pattern, $host, $methods);
        }

        $config->replaceArgument(2, $matcher ? (string) $matcher : null);
        $config->replaceArgument(3, $firewall['security']);

        // Security disabled?
        if (false === $firewall['security']) {
            return [$matcher, [], null, null];
        }

        $config->replaceArgument(4, $firewall['stateless']);

        // Provider id (take the first registered provider if none defined)
        $defaultProvider = null;
        if (isset($firewall['provider'])) {
            if (!isset($providerIds[$normalizedName = str_replace('-', '_', $firewall['provider'])])) {
                throw new InvalidConfigurationException(sprintf('Invalid firewall "%s": user provider "%s" not found.', $id, $firewall['provider']));
            }
            $defaultProvider = $providerIds[$normalizedName];
        } elseif (1 === \count($providerIds)) {
            $defaultProvider = reset($providerIds);
        }

        $config->replaceArgument(5, $defaultProvider);

        // Register listeners
        $listeners = [];
        $listenerKeys = [];

        // Channel listener
        $listeners[] = new Reference('security.channel_listener');

        $contextKey = null;
        $contextListenerId = null;
        // Context serializer listener
        if (false === $firewall['stateless']) {
            $contextKey = $id;
            if (isset($firewall['context'])) {
                $contextKey = $firewall['context'];
            }

            if (!$logoutOnUserChange = $firewall['logout_on_user_change']) {
                @trigger_error(sprintf('Not setting "logout_on_user_change" to true on firewall "%s" is deprecated as of 3.4, it will always be true in 4.0.', $id), \E_USER_DEPRECATED);
            }

            if (isset($this->logoutOnUserChangeByContextKey[$contextKey]) && $this->logoutOnUserChangeByContextKey[$contextKey][1] !== $logoutOnUserChange) {
                throw new InvalidConfigurationException(sprintf('Firewalls "%s" and "%s" need to have the same value for option "logout_on_user_change" as they are sharing the context "%s".', $this->logoutOnUserChangeByContextKey[$contextKey][0], $id, $contextKey));
            }

            $this->logoutOnUserChangeByContextKey[$contextKey] = [$id, $logoutOnUserChange];
            $listeners[] = new Reference($contextListenerId = $this->createContextListener($container, $contextKey, $logoutOnUserChange));
            $sessionStrategyId = 'security.authentication.session_strategy';
        } else {
            $this->statelessFirewallKeys[] = $id;
            $sessionStrategyId = 'security.authentication.session_strategy_noop';
        }
        $container->setAlias(new Alias('security.authentication.session_strategy.'.$id, false), $sessionStrategyId);

        $config->replaceArgument(6, $contextKey);

        // Logout listener
        $logoutListenerId = null;
        if (isset($firewall['logout'])) {
            $logoutListenerId = 'security.logout_listener.'.$id;
            $logoutListener = $container->setDefinition($logoutListenerId, new ChildDefinition('security.logout_listener'));
            $logoutListener->replaceArgument(3, [
                'csrf_parameter' => $firewall['logout']['csrf_parameter'],
                'csrf_token_id' => $firewall['logout']['csrf_token_id'],
                'logout_path' => $firewall['logout']['path'],
            ]);

            // add logout success handler
            if (isset($firewall['logout']['success_handler'])) {
                $logoutSuccessHandlerId = $firewall['logout']['success_handler'];
            } else {
                $logoutSuccessHandlerId = 'security.logout.success_handler.'.$id;
                $logoutSuccessHandler = $container->setDefinition($logoutSuccessHandlerId, new ChildDefinition('security.logout.success_handler'));
                $logoutSuccessHandler->replaceArgument(1, $firewall['logout']['target']);
            }
            $logoutListener->replaceArgument(2, new Reference($logoutSuccessHandlerId));

            // add CSRF provider
            if (isset($firewall['logout']['csrf_token_generator'])) {
                $logoutListener->addArgument(new Reference($firewall['logout']['csrf_token_generator']));
            }

            // add session logout handler
            if (true === $firewall['logout']['invalidate_session'] && false === $firewall['stateless']) {
                $logoutListener->addMethodCall('addHandler', [new Reference('security.logout.handler.session')]);
            }

            // add cookie logout handler
            if (\count($firewall['logout']['delete_cookies']) > 0) {
                $cookieHandlerId = 'security.logout.handler.cookie_clearing.'.$id;
                $cookieHandler = $container->setDefinition($cookieHandlerId, new ChildDefinition('security.logout.handler.cookie_clearing'));
                $cookieHandler->addArgument($firewall['logout']['delete_cookies']);

                $logoutListener->addMethodCall('addHandler', [new Reference($cookieHandlerId)]);
            }

            // add custom handlers
            foreach ($firewall['logout']['handlers'] as $handlerId) {
                $logoutListener->addMethodCall('addHandler', [new Reference($handlerId)]);
            }

            // register with LogoutUrlGenerator
            $container
                ->getDefinition('security.logout_url_generator')
                ->addMethodCall('registerListener', [
                    $id,
                    $firewall['logout']['path'],
                    $firewall['logout']['csrf_token_id'],
                    $firewall['logout']['csrf_parameter'],
                    isset($firewall['logout']['csrf_token_generator']) ? new Reference($firewall['logout']['csrf_token_generator']) : null,
                    false === $firewall['stateless'] && isset($firewall['context']) ? $firewall['context'] : null,
                ])
            ;
        }

        // Determine default entry point
        $configuredEntryPoint = isset($firewall['entry_point']) ? $firewall['entry_point'] : null;

        // Authentication listeners
        list($authListeners, $defaultEntryPoint) = $this->createAuthenticationListeners($container, $id, $firewall, $authenticationProviders, $defaultProvider, $providerIds, $configuredEntryPoint, $contextListenerId);

        $config->replaceArgument(7, $configuredEntryPoint ?: $defaultEntryPoint);

        $listeners = array_merge($listeners, $authListeners);

        // Switch user listener
        if (isset($firewall['switch_user'])) {
            $listenerKeys[] = 'switch_user';
            $listeners[] = new Reference($this->createSwitchUserListener($container, $id, $firewall['switch_user'], $defaultProvider, $firewall['stateless'], $providerIds));
        }

        // Access listener
        $listeners[] = new Reference('security.access_listener');

        // Exception listener
        $exceptionListener = new Reference($this->createExceptionListener($container, $firewall, $id, $configuredEntryPoint ?: $defaultEntryPoint, $firewall['stateless']));

        $config->replaceArgument(8, isset($firewall['access_denied_handler']) ? $firewall['access_denied_handler'] : null);
        $config->replaceArgument(9, isset($firewall['access_denied_url']) ? $firewall['access_denied_url'] : null);

        $container->setAlias('security.user_checker.'.$id, new Alias($firewall['user_checker'], false));

        foreach ($this->factories as $position) {
            foreach ($position as $factory) {
                $key = str_replace('-', '_', $factory->getKey());
                if (\array_key_exists($key, $firewall)) {
                    $listenerKeys[] = $key;
                }
            }
        }

        if (isset($firewall['anonymous'])) {
            $listenerKeys[] = 'anonymous';
        }

        $config->replaceArgument(10, $listenerKeys);
        $config->replaceArgument(11, isset($firewall['switch_user']) ? $firewall['switch_user'] : null);

        return [$matcher, $listeners, $exceptionListener, null !== $logoutListenerId ? new Reference($logoutListenerId) : null];
    }

    private function createContextListener($container, $contextKey, $logoutUserOnChange)
    {
        if (isset($this->contextListeners[$contextKey])) {
            return $this->contextListeners[$contextKey];
        }

        $listenerId = 'security.context_listener.'.\count($this->contextListeners);
        $listener = $container->setDefinition($listenerId, new ChildDefinition('security.context_listener'));
        $listener->replaceArgument(2, $contextKey);
        $listener->addMethodCall('setLogoutOnUserChange', [$logoutUserOnChange]);

        return $this->contextListeners[$contextKey] = $listenerId;
    }

    private function createAuthenticationListeners($container, $id, $firewall, &$authenticationProviders, $defaultProvider, array $providerIds, $defaultEntryPoint, $contextListenerId = null)
    {
        $listeners = [];
        $hasListeners = false;

        foreach ($this->listenerPositions as $position) {
            foreach ($this->factories[$position] as $factory) {
                $key = str_replace('-', '_', $factory->getKey());

                if (isset($firewall[$key])) {
                    if (isset($firewall[$key]['provider'])) {
                        if (!isset($providerIds[$normalizedName = str_replace('-', '_', $firewall[$key]['provider'])])) {
                            throw new InvalidConfigurationException(sprintf('Invalid firewall "%s": user provider "%s" not found.', $id, $firewall[$key]['provider']));
                        }
                        $userProvider = $providerIds[$normalizedName];
                    } elseif ('remember_me' === $key) {
                        // RememberMeFactory will use the firewall secret when created
                        $userProvider = null;
                        if ($contextListenerId) {
                            $container->getDefinition($contextListenerId)->addTag('security.remember_me_aware', ['id' => $id, 'provider' => 'none']);
                        }
                    } else {
                        $userProvider = $defaultProvider ?: $this->getFirstProvider($id, $key, $providerIds);
                    }

                    list($provider, $listenerId, $defaultEntryPoint) = $factory->create($container, $id, $firewall[$key], $userProvider, $defaultEntryPoint);

                    $listeners[] = new Reference($listenerId);
                    $authenticationProviders[] = $provider;
                    $hasListeners = true;
                }
            }
        }

        // Anonymous
        if (isset($firewall['anonymous'])) {
            if (null === $firewall['anonymous']['secret']) {
                $firewall['anonymous']['secret'] = new Parameter('container.build_hash');
            }

            $listenerId = 'security.authentication.listener.anonymous.'.$id;
            $container
                ->setDefinition($listenerId, new ChildDefinition('security.authentication.listener.anonymous'))
                ->replaceArgument(1, $firewall['anonymous']['secret'])
            ;

            $listeners[] = new Reference($listenerId);

            $providerId = 'security.authentication.provider.anonymous.'.$id;
            $container
                ->setDefinition($providerId, new ChildDefinition('security.authentication.provider.anonymous'))
                ->replaceArgument(0, $firewall['anonymous']['secret'])
            ;

            $authenticationProviders[] = $providerId;
            $hasListeners = true;
        }

        if (false === $hasListeners) {
            throw new InvalidConfigurationException(sprintf('No authentication listener registered for firewall "%s".', $id));
        }

        return [$listeners, $defaultEntryPoint];
    }

    private function createEncoders($encoders, ContainerBuilder $container)
    {
        $encoderMap = [];
        foreach ($encoders as $class => $encoder) {
            $encoderMap[$class] = $this->createEncoder($encoder);
        }

        $container
            ->getDefinition('security.encoder_factory.generic')
            ->setArguments([$encoderMap])
        ;
    }

    private function createEncoder($config)
    {
        // a custom encoder service
        if (isset($config['id'])) {
            return new Reference($config['id']);
        }

        // plaintext encoder
        if ('plaintext' === $config['algorithm']) {
            $arguments = [$config['ignore_case']];

            return [
                'class' => 'Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder',
                'arguments' => $arguments,
            ];
        }

        // pbkdf2 encoder
        if ('pbkdf2' === $config['algorithm']) {
            return [
                'class' => 'Symfony\Component\Security\Core\Encoder\Pbkdf2PasswordEncoder',
                'arguments' => [
                    $config['hash_algorithm'],
                    $config['encode_as_base64'],
                    $config['iterations'],
                    $config['key_length'],
                ],
            ];
        }

        // bcrypt encoder
        if ('bcrypt' === $config['algorithm']) {
            return [
                'class' => 'Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder',
                'arguments' => [$config['cost']],
            ];
        }

        // Argon2i encoder
        if ('argon2i' === $config['algorithm']) {
            if (!Argon2iPasswordEncoder::isSupported()) {
                throw new InvalidConfigurationException('Argon2i algorithm is not supported. Please install the libsodium extension or upgrade to PHP 7.2+.');
            }

            return [
                'class' => 'Symfony\Component\Security\Core\Encoder\Argon2iPasswordEncoder',
                'arguments' => [],
            ];
        }

        // run-time configured encoder
        return $config;
    }

    // Parses user providers and returns an array of their ids
    private function createUserProviders($config, ContainerBuilder $container)
    {
        $providerIds = [];
        foreach ($config['providers'] as $name => $provider) {
            $id = $this->createUserDaoProvider($name, $provider, $container);
            $providerIds[str_replace('-', '_', $name)] = $id;
        }

        return $providerIds;
    }

    // Parses a <provider> tag and returns the id for the related user provider service
    private function createUserDaoProvider($name, $provider, ContainerBuilder $container)
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

    private function getUserProviderId($name)
    {
        return 'security.user.provider.concrete.'.strtolower($name);
    }

    private function createExceptionListener($container, $config, $id, $defaultEntryPoint, $stateless)
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

    private function createSwitchUserListener($container, $id, $config, $defaultProvider, $stateless, $providerIds)
    {
        $userProvider = isset($config['provider']) ? $this->getUserProviderId($config['provider']) : ($defaultProvider ?: $this->getFirstProvider($id, 'switch_user', $providerIds));

        // in 4.0, ignore the `switch_user.stateless` key if $stateless is `true`
        if ($stateless && false === $config['stateless']) {
            @trigger_error(sprintf('Firewall "%s" is configured as "stateless" but the "switch_user.stateless" key is set to false. Both should have the same value, the firewall\'s "stateless" value will be used as default value for the "switch_user.stateless" key in 4.0.', $id), \E_USER_DEPRECATED);
        }

        $switchUserListenerId = 'security.authentication.switchuser_listener.'.$id;
        $listener = $container->setDefinition($switchUserListenerId, new ChildDefinition('security.authentication.switchuser_listener'));
        $listener->replaceArgument(1, new Reference($userProvider));
        $listener->replaceArgument(2, new Reference('security.user_checker.'.$id));
        $listener->replaceArgument(3, $id);
        $listener->replaceArgument(6, $config['parameter']);
        $listener->replaceArgument(7, $config['role']);
        $listener->replaceArgument(9, $config['stateless']);

        return $switchUserListenerId;
    }

    private function createExpression($container, $expression)
    {
        if (isset($this->expressions[$id = 'security.expression.'.ContainerBuilder::hash($expression)])) {
            return $this->expressions[$id];
        }

        $container
            ->register($id, 'Symfony\Component\ExpressionLanguage\SerializedParsedExpression')
            ->setPublic(false)
            ->addArgument($expression)
            ->addArgument(serialize($this->getExpressionLanguage()->parse($expression, ['token', 'user', 'object', 'roles', 'request', 'trust_resolver'])->getNodes()))
        ;

        return $this->expressions[$id] = new Reference($id);
    }

    private function createRequestMatcher($container, $path = null, $host = null, $methods = [], $ip = null, array $attributes = [])
    {
        if ($methods) {
            $methods = array_map('strtoupper', (array) $methods);
        }

        $id = 'security.request_matcher.'.ContainerBuilder::hash([$path, $host, $methods, $ip, $attributes]);

        if (isset($this->requestMatchers[$id])) {
            return $this->requestMatchers[$id];
        }

        // only add arguments that are necessary
        $arguments = [$path, $host, $methods, $ip, $attributes];
        while (\count($arguments) > 0 && !end($arguments)) {
            array_pop($arguments);
        }

        $container
            ->register($id, 'Symfony\Component\HttpFoundation\RequestMatcher')
            ->setPublic(false)
            ->setArguments($arguments)
        ;

        return $this->requestMatchers[$id] = new Reference($id);
    }

    public function addSecurityListenerFactory(SecurityFactoryInterface $factory)
    {
        $this->factories[$factory->getPosition()][] = $factory;
    }

    public function addUserProviderFactory(UserProviderFactoryInterface $factory)
    {
        $this->userProviderFactories[] = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/security';
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        // first assemble the factories
        return new MainConfiguration($this->factories, $this->userProviderFactories);
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new ExpressionLanguage();
        }

        return $this->expressionLanguage;
    }

    /**
     * @deprecated since version 3.4, to be removed in 4.0
     */
    private function getFirstProvider($firewallName, $listenerName, array $providerIds)
    {
        @trigger_error(sprintf('Listener "%s" on firewall "%s" has no "provider" set but multiple providers exist. Using the first configured provider (%s) is deprecated since Symfony 3.4 and will throw an exception in 4.0, set the "provider" key on the firewall instead.', $listenerName, $firewallName, $first = array_keys($providerIds)[0]), \E_USER_DEPRECATED);

        return $providerIds[$first];
    }
}
