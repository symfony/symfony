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

use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * SecurityExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SecurityExtension extends Extension
{
    private $requestMatchers = array();
    private $contextListeners = array();
    private $listenerPositions = array('pre_auth', 'form', 'http', 'remember_me');
    private $factories;

    public function load(array $configs, ContainerBuilder $container)
    {
        if (!array_filter($configs)) {
            return;
        }

        // process and flatten the configs
        $config = $this->processConfigs($configs, $container->getParameterBag());

        // load services
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('security.xml');
        $loader->load('security_listeners.xml');
        $loader->load('security_rememberme.xml');
        $loader->load('templating_php.xml');
        $loader->load('templating_twig.xml');
        $loader->load('collectors.xml');

        // set some global scalars
        $container->setParameter('security.access.denied_url', $config['access_denied_url']);
        $container->setParameter('security.authentication.session_strategy.strategy', $config['session_fixation_strategy']);
        $container
            ->getDefinition('security.access.decision_manager')
            ->addArgument($config['access_decision_manager']['strategy'])
            ->addArgument($config['access_decision_manager']['allow_if_all_abstain'])
            ->addArgument($config['access_decision_manager']['allow_if_equal_granted_denied'])
        ;
        $container->setParameter('security.access.always_authenticate_before_granting', $config['always_authenticate_before_granting']);

        $this->createFirewalls($config, $container);
        $this->createAuthorization($config, $container);
        $this->createRoleHierarchy($config, $container);

        if ($config['encoders']) {
            $this->createEncoders($config['encoders'], $container);
        }

        // load ACL
        if (isset($config['acl'])) {
            $this->aclLoad($config['acl'], $container);
        }

        // add some required classes for compilation
        $this->addClassesToCompile(array(
            'Symfony\\Component\\Security\\Http\\Firewall',
            'Symfony\\Component\\Security\\Http\\FirewallMapInterface',
            'Symfony\\Component\\Security\\Core\\SecurityContext',
            'Symfony\\Component\\Security\\Core\\SecurityContextInterface',
            'Symfony\\Component\\Security\\Core\\User\\UserProviderInterface',
            'Symfony\\Component\\Security\\Core\\Authentication\\AuthenticationProviderManager',
            'Symfony\\Component\\Security\\Core\\Authentication\\AuthenticationManagerInterface',
            'Symfony\\Component\\Security\\Core\\Authorization\\AccessDecisionManager',
            'Symfony\\Component\\Security\\Core\\Authorization\\AccessDecisionManagerInterface',
            'Symfony\\Component\\Security\\Core\\Authorization\\Voter\\VoterInterface',

            'Symfony\\Bundle\\SecurityBundle\\Security\\FirewallMap',
            'Symfony\\Bundle\\SecurityBundle\\Security\\FirewallContext',

            'Symfony\\Component\\HttpFoundation\\RequestMatcher',
            'Symfony\\Component\\HttpFoundation\\RequestMatcherInterface',
        ));
    }

    /**
     * Takes in the config arrays, validates and flattens them into one array
     *
     * @param array $configs The raw array of configuration arrays
     * @param ParameterBagInterface $parameterBag
     * @return array The flattened configuration
     */
    private function processConfigs(array $configs, ParameterBagInterface $parameterBag)
    {
        $processor = new Processor();

        // first assemble the factories
        $factoriesConfig = new FactoryConfiguration();
        $config = $processor->processConfiguration($factoriesConfig, $configs);

        $factories = $this->createListenerFactories($parameterBag, $config['factories']);

        // normalize and merge the actual configuration
        $mainConfig = new MainConfiguration($factories);

        return $processor->processConfiguration($mainConfig, $configs);
    }

    private function aclLoad($config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('security_acl.xml');

        if (isset($config['cache']['id'])) {
            $container->setAlias('security.acl.cache', $config['cache']['id']);
        }
        $container->getDefinition('security.acl.voter.basic_permissions')->addArgument($config['voter']['allow_if_object_identity_unavailable']);

        // custom ACL provider
        if (isset($config['provider'])) {
            $container->setAlias('security.acl.provider', $config['provider']);
            return;
        }

        $this->configureDbalAclProvider($config, $container, $loader);
    }

    private function configureDbalAclProvider(array $config, ContainerBuilder $container, $loader)
    {
        $loader->load('security_acl_dbal.xml');

        if (isset($config['connection'])) {
            $container->setAlias('security.acl.dbal.connection', sprintf('doctrine.dbal.%s_connection', $config['connection']));
        }
        $container->getDefinition('security.acl.cache.doctrine')->addArgument($config['cache']['prefix']);

        $container->setParameter('security.acl.dbal.class_table_name', $config['tables']['class']);
        $container->setParameter('security.acl.dbal.entry_table_name', $config['tables']['entry']);
        $container->setParameter('security.acl.dbal.oid_table_name', $config['tables']['object_identity']);
        $container->setParameter('security.acl.dbal.oid_ancestors_table_name', $config['tables']['object_identity_ancestors']);
        $container->setParameter('security.acl.dbal.sid_table_name', $config['tables']['security_identity']);
    }

    /**
     * Loads the web configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */

    private function createRoleHierarchy($config, ContainerBuilder $container)
    {
        if (!isset($config['role_hierarchy'])) {
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

        $this->addClassesToCompile(array(
            'Symfony\\Component\\Security\\Http\\AccessMap',
        ));

        foreach ($config['access_control'] as $access) {
            $matcher = $this->createRequestMatcherReference(
                $container,
                $access['path'],
                $access['host'],
                count($access['methods']) === 0 ? null : $access['methods'],
                $access['ip']
            );

            $container->getDefinition('security.access_map')
                      ->addMethodCall('add', array($matcher, $access['roles'], $access['requires_channel']));
        }
    }

    /**
     * Configures everything in the service container necessary for the configured firewalls
     *
     * @param array $config The security configuration array
     * @param ContainerBuilder $container
     */
    private function createFirewalls(array $config, ContainerBuilder $container)
    {
        if (!isset($config['firewalls'])) {
            return;
        }

        $firewalls = $config['firewalls'];
        $userProviderIds = $this->createUserProviders($config['providers'], $container);

        // the ContextListener needs the user providers to load users from the session
        $definition = $container->getDefinition('security.context_listener');
        $arguments = $definition->getArguments();
        $userProviders = array();
        foreach ($userProviderIds as $userProviderId) {
            $userProviders[] = new Reference($userProviderId);
        }
        $arguments[1] = $userProviders;
        $definition->setArguments($arguments);

        // create and return the security factory objects
        $factories = $this->createListenerFactories($container->getParameterBag(), $config['factories']);

        // load firewall map
        $mapDef = $container->getDefinition('security.firewall.map');
        $map = $authenticationProviders = array();
        foreach ($firewalls as $name => $firewall) {
            list($matcher, $listeners, $exceptionListener) = $this->createFirewall($container, $name, $firewall, $authenticationProviders, $userProviderIds, $factories);

            $contextId = 'security.firewall.map.context.'.$name;
            $context = $container->setDefinition($contextId, new DefinitionDecorator('security.firewall.context'));
            $context
                ->replaceArgument(0, $listeners)
                ->replaceArgument(1, $exceptionListener)
            ;
            $map[$contextId] = $matcher;
        }
        $mapDef->replaceArgument(1, $map);

        // add authentication providers to authentication manager
        $authenticationProviders = array_map(function($id) {
            return new Reference($id);
        }, array_values(array_unique($authenticationProviders)));
        $container
            ->getDefinition('security.authentication.manager')
            ->replaceArgument(0, $authenticationProviders)
        ;
    }

    /**
     * Creates a firewall, which is three parts:
     *
     *   * A Reference to the request matcher service
     *   * An array of Reference objects to firewall listening services
     *   * A Reference to an exception listener for the firewall
     *
     * This also loads all of the authentication providers, which correspond
     * to each firewall listener that's concerned with authentication. This
     * works because an authentication listeners is called and passes a
     * particular token to the authentication manager. The authentication
     * manager then passes the token to the corresponding authentication
     * provider to pass back the authenticated token (or throw an exception).
     *
     * @param ContainerBuilder $container
     * @param string $firewallName The name of the firewall
     * @param array  $firewallConfig     The firewall array configuration
     * @param array  $authenticationProviders The authentication providers
     * @param array  $providerIds  The service ids of the user providers
     * @param array   $factories    Array of security factory objects
     *
     * @return array An array of the Reference to the request matcher, listeners, and exception listener
     */
    private function createFirewall(ContainerBuilder $container, $firewallName, $firewallConfig, &$authenticationProviders, $providerIds, array $factories)
    {
        // request matcher
        $matcher = null;
        if (isset($firewallConfig['request_matcher'])) {
            // Use the request_matcher service id specified
            $matcher = new Reference($firewallConfig['request_matcher']);
        } else if (isset($firewallConfig['pattern'])) {
            // Create a request matcher based on the given firewall pattern
            $matcher = $this->createRequestMatcherReference($container, $firewallConfig['pattern']);
        }

        // Security disabled?
        if (false === $firewallConfig['security']) {
            // return the request matcher, but no listeners to act on the request
            return array($matcher, array(), null);
        }

        // Determine the user provider for the firewall (use the first if none specified)
        if (isset($firewallConfig['provider'])) {
            $defaultUserProviderId = $this->getUserProviderId($firewallConfig['provider']);

            // make sure the referenced provider id is valid
            if (!in_array($defaultUserProviderId, $providerIds)) {
                throw new \LogicException(sprintf('"%s" is not a valid provider under firewall "%s".', $firewallConfig['provider'], $firewallName));
            }
        } else {
            $defaultUserProviderId = reset($providerIds);
        }

        // Register listeners
        $listeners = array();

        // Add the channel listener to this firewall (handles http <-> https)
        $listeners[] = new Reference('security.channel_listener');

        // Add the Context listener (handles storing authentication on the listener)
        if (false === $firewallConfig['stateless']) {
            $contextKey = isset($firewallConfig['context']) ? $firewallConfig['context'] : $firewallName;

            $listeners[] = new Reference($this->createContextListener($container, $contextKey));
        }

        // add and configure the Logout listener
        if (isset($firewallConfig['logout'])) {
            $listenerId = 'security.logout_listener.'.$firewallName;
            $listener = $container->setDefinition($listenerId, new DefinitionDecorator('security.logout_listener'));
            $listener->replaceArgument(1, $firewallConfig['logout']['path']);
            $listener->replaceArgument(2, $firewallConfig['logout']['target']);
            $listeners[] = new Reference($listenerId);

            // add logout success handler
            if (isset($firewallConfig['logout']['success_handler'])) {
                $listener->replaceArgument(3, new Reference($firewallConfig['logout']['success_handler']));
            }

            // to totally invalidate the session, add the handler that does that
            if (true === $firewallConfig['logout']['invalidate_session'] && false === $firewallConfig['stateless']) {
                $listener->addMethodCall('addHandler', array(new Reference('security.logout.handler.session')));
            }

            // If at least one cookie is set to be deleted, add the cookie logout handler
            if (count($firewallConfig['logout']['delete_cookies']) > 0) {
                $cookieHandlerId = 'security.logout.handler.cookie_clearing.'.$firewallName;
                $cookieHandler = $container->setDefinition($cookieHandlerId, new DefinitionDecorator('security.logout.handler.cookie_clearing'));
                $cookieHandler->addArgument($firewallConfig['logout']['delete_cookies']);

                $listener->addMethodCall('addHandler', array(new Reference($cookieHandlerId)));
            }

            // add custom handlers
            foreach ($firewallConfig['logout']['handlers'] as $handlerId) {
                $listener->addMethodCall('addHandler', array(new Reference($handlerId)));
            }
        }

        // Authentication listeners
        list($authListeners, $defaultEntryPoint) = $this->createAuthenticationListeners($container, $firewallName, $firewallConfig, $authenticationProviders, $defaultUserProviderId, $providerIds, $factories);

        $listeners = array_merge($listeners, $authListeners);

        // Add the Access listener (enforces access controls)
        $listeners[] = new Reference('security.access_listener');

        // Add the Switch user listener
        if (isset($firewallConfig['switch_user'])) {
            $listeners[] = new Reference($this->createSwitchUserListener($container, $firewallName, $firewallConfig['switch_user'], $defaultUserProviderId));
        }

        // Allow the user to override the entry point
        $entryPoint = isset($firewallConfig['entry_point']) ? $firewallConfig['entry_point'] : $defaultEntryPoint;

        // Add the exception listener (which initiates the authentication when access is denied)
        $exceptionListener = new Reference($this->createExceptionListener(
            $container,
            $firewallConfig,
            $firewallName,
            $entryPoint
        ));

        return array($matcher, $listeners, $exceptionListener);
    }

    /**
     * Returns a service id that refers to a ContextListener with the given
     * context key.
     *
     * @param ContainerBuilder $container
     * @param string $contextKey The identifier for this context listener
     *
     * @return string The service id to the context listener
     */
    private function createContextListener(ContainerBuilder $container, $contextKey)
    {
        if (isset($this->contextListeners[$contextKey])) {
            return $this->contextListeners[$contextKey];
        }

        $listenerId = 'security.context_listener.'.$contextKey;
        $listener = $container->setDefinition($listenerId, new DefinitionDecorator('security.context_listener'));
        $listener->replaceArgument(2, $contextKey);

        return $this->contextListeners[$contextKey] = $listenerId;
    }

    /**
     * Reads and loads any authentication factories specified in the firewall configuration.
     *
     * The goal of this method is to return an array of authentication Reference
     * listeners as well as the default entry point service id.
     *
     * Each factory represents another listener on the firewall. Each factory
     * also returns an additional authentication provider, which is pushed
     * onto the authenticationProviders array.
     *
     * @param ContainerBuilder $container
     * @param string $firewallName The name of the firewall being configured
     * @param array $firewallConfig The raw firewall configuration array
     * @param array $authenticationProviders The authentication providers
     * @param string $defaultUserProviderId The service id for the default user provider
     * @param array $providerIds The available, valid user provider ids
     * @param array $factories The array of security factory objects
     *
     * @return array of listener references and the default entry point service id
     */
    private function createAuthenticationListeners(ContainerBuilder $container, $firewallName, $firewallConfig, &$authenticationProviders, $defaultUserProviderId, array $providerIds, array $factories)
    {
        $listeners = array();
        $hasListeners = false;
        $defaultEntryPoint = null;

        foreach ($this->listenerPositions as $position) {
            foreach ($factories[$position] as $factory) {
                // normalize the factory keys
                $key = str_replace('-', '_', $factory->getKey());

                // if a particular firewall's key is included in the config, configure it
                if (isset($firewallConfig[$key])) {
                    $userProvider = isset($firewallConfig[$key]['provider']) ? $this->getUserProviderId($firewallConfig[$key]['provider']) : $defaultUserProviderId;

                    // make sure the referenced provider id is valid
                    if (!in_array($userProvider, $providerIds)) {
                        throw new \LogicException(sprintf('"%s" is not a valid provider under firewall "%s", listener "%s".', $firewallConfig[$key]['provider'], $firewallName, $key));
                    }

                    list($provider, $listenerId, $defaultEntryPoint) = $factory->create($container, $firewallName, $firewallConfig[$key], $userProvider, $defaultEntryPoint);

                    // save the authentication listener and provider
                    $listeners[] = new Reference($listenerId);
                    $authenticationProviders[] = $provider;
                    $hasListeners = true;
                }
            }
        }

        // Anonymous
        if (isset($firewallConfig['anonymous'])) {
            $listenerId = 'security.authentication.listener.anonymous.'.$firewallName;
            $container
                ->setDefinition($listenerId, new DefinitionDecorator('security.authentication.listener.anonymous'))
                ->replaceArgument(1, $firewallConfig['anonymous']['key'])
            ;

            $listeners[] = new Reference($listenerId);

            $providerId = 'security.authentication.provider.anonymous.'.$firewallName;
            $container
                ->setDefinition($providerId, new DefinitionDecorator('security.authentication.provider.anonymous'))
                ->replaceArgument(0, $firewallConfig['anonymous']['key'])
            ;

            $authenticationProviders[] = $providerId;
            $hasListeners = true;
        }

        if (false === $hasListeners) {
            throw new \LogicException(sprintf('No authentication listener registered for firewall "%s".', $firewallName));
        }

        return array($listeners, $defaultEntryPoint);
    }

    private function createEncoders($encoders, ContainerBuilder $container)
    {
        $encoderMap = array();
        foreach ($encoders as $class => $encoder) {
            $encoderMap[$class] = $this->createEncoder($encoder, $container);
        }

        $container
            ->getDefinition('security.encoder_factory.generic')
            ->setArguments(array($encoderMap))
        ;
    }

    private function createEncoder($config, ContainerBuilder $container)
    {
        // a custom encoder service
        if (isset($config['id'])) {
            return new Reference($config['id']);
        }

        // plaintext encoder
        if ('plaintext' === $config['algorithm']) {
            $arguments = array($config['ignore_case']);

            return array(
                'class' => new Parameter('security.encoder.plain.class'),
                'arguments' => $arguments,
            );
        }

        // message digest encoder
        $arguments = array(
            $config['algorithm'],
            $config['encode_as_base64'],
            $config['iterations'],
        );

        return array(
            'class' => new Parameter('security.encoder.digest.class'),
            'arguments' => $arguments,
        );
    }

    // Parses user providers and returns an array of their ids
    private function createUserProviders($providersConfig, ContainerBuilder $container)
    {
        $providerIds = array();
        foreach ($providersConfig as $name => $provider) {
            $id = $this->getUserDaoProviderId($name, $provider, $container);
            $providerIds[] = $id;
        }

        return $providerIds;
    }

    // Parses a <provider> tag and returns the id for the related user provider service
    private function getUserDaoProviderId($name, $provider, ContainerBuilder $container)
    {
        $name = $this->getUserProviderId(strtolower($name));

        // Existing DAO service provider
        if (isset($provider['id'])) {
            $container->setAlias($name, new Alias($provider['id'], false));

            return $name;
        }

        // Chain provider
        if ($provider['providers']) {
            $providers = array();
            foreach ($provider['providers'] as $providerName) {
                $providers[] = new Reference($this->getUserProviderId(strtolower($providerName)));
            }

            $container
                ->setDefinition($name, new DefinitionDecorator('security.user.provider.chain'))
                ->addArgument($providers)
            ;

            return $name;
        }

        // Doctrine Entity DAO provider
        if (isset($provider['entity'])) {
            $container
                ->setDefinition($name, new DefinitionDecorator('security.user.provider.entity'))
                ->addArgument($provider['entity']['class'])
                ->addArgument($provider['entity']['property'])
            ;

            return $name;
        }

        // In-memory DAO provider
        $definition = $container->setDefinition($name, new DefinitionDecorator('security.user.provider.in_memory'));
        foreach ($provider['users'] as $username => $user) {
            $userId = $name.'_'.$username;

            $container
                ->setDefinition($userId, new DefinitionDecorator('security.user.provider.in_memory.user'))
                ->setArguments(array($username, (string)$user['password'], $user['roles']))
            ;

            $definition->addMethodCall('createUser', array(new Reference($userId)));
        }

        return $name;
    }

    private function getUserProviderId($name)
    {
        return 'security.user.provider.concrete.'.$name;
    }

    /**
     * Configures and returns the exception listener service id for the given
     * firewall based on the firewall configuration and entry point.
     *
     * @param ContainerBuilder $container
     * @param array $firewallConfig The firewall configuration
     * @param string $firewallName The name of the firewall
     * @param $entryPointId The service id to the entry point to use
     *
     * @return string The exception listener service id
     */
    private function createExceptionListener(ContainerBuilder $container, $firewallConfig, $firewallName, $entryPointId)
    {
        $exceptionListenerId = 'security.exception_listener.'.$firewallName;
        $listener = $container->setDefinition($exceptionListenerId, new DefinitionDecorator('security.exception_listener'));
        $listener->replaceArgument(2, null === $entryPointId ? null : new Reference($entryPointId));

        // access denied handler setup
        if (isset($firewallConfig['access_denied_handler'])) {
            $listener->replaceArgument(4, new Reference($firewallConfig['access_denied_handler']));
        } else if (isset($firewallConfig['access_denied_url'])) {
            $listener->replaceArgument(3, $firewallConfig['access_denied_url']);
        }

        return $exceptionListenerId;
    }

    private function createSwitchUserListener($container, $firewallName, $switchUserConfig, $defaultProvider)
    {
        $userProvider = isset($switchUserConfig['provider']) ? $this->getUserProviderId($switchUserConfig['provider']) : $defaultProvider;

        $switchUserListenerId = 'security.authentication.switchuser_listener.'.$firewallName;
        $listener = $container->setDefinition($switchUserListenerId, new DefinitionDecorator('security.authentication.switchuser_listener'));
        $listener->replaceArgument(1, new Reference($userProvider));
        $listener->replaceArgument(3, $firewallName);
        $listener->replaceArgument(6, $switchUserConfig['parameter']);
        $listener->replaceArgument(7, $switchUserConfig['role']);

        return $switchUserListenerId;
    }

    /**
     * Creates a RequestMatcher instance based on the given parameters and
     * returns a Reference referring to the service.
     *
     * @param ContainerBuilder $container
     * @param string $path The path/pattern that the request matcher should match
     * @param string $host The host pattern to match
     * @param string|array $methods The array of HTTP methods to match
     * @param string $ip A specific ip address or range to match
     * @param array $attributes Any other request attributes to match
     * @return array|\Symfony\Component\DependencyInjection\Reference
     *
     * @return Reference
     */
    private function createRequestMatcherReference(ContainerBuilder $container, $path, $host = null, $methods = null, $ip = null, array $attributes = array())
    {
        $serialized = serialize(array($path, $host, $methods, $ip, $attributes));
        $id = 'security.request_matcher.'.md5($serialized).sha1($serialized);

        if (isset($this->requestMatchers[$id])) {
            return $this->requestMatchers[$id];
        }

        // only add arguments that are necessary
        $arguments = array($path, $host, $methods, $ip, $attributes);
        while (count($arguments) > 0 && !end($arguments)) {
            array_pop($arguments);
        }

        $container
            ->register($id, '%security.matcher.class%')
            ->setPublic(false)
            ->setArguments($arguments)
        ;

        return $this->requestMatchers[$id] = new Reference($id);
    }

    /**
     * Returns an array of security factory objects.
     *
     * This function is responsible for loading all of the given security
     * factory resource files (including those under the "factories" key.
     *
     * This looks for services in those resources tagged with "security.listener.factory"
     * and returns an array of those instantiated objects. Those services
     * are sub-grouped in the array based on their position (e.g. pre_auth).
     *
     * @return array
     */
    private function createListenerFactories(ParameterBagInterface $parameterBag, $factoriesConfig)
    {
        // only load the factories once
        if (null !== $this->factories) {
            return $this->factories;
        }

        // load service templates
        $c = new ContainerBuilder();

        $locator = new FileLocator(__DIR__.'/../Resources/config');
        $resolver = new LoaderResolver(array(
            new XmlFileLoader($c, $locator),
            new YamlFileLoader($c, $locator),
            new PhpFileLoader($c, $locator),
        ));
        $loader = new DelegatingLoader($resolver);

        $loader->load('security_factories.xml');

        // load user-created listener factories
        foreach ($factoriesConfig as $factory) {
            $loader->load($parameterBag->resolveValue($factory));
        }

        $tags = $c->findTaggedServiceIds('security.listener.factory');

        $factories = array();
        foreach ($this->listenerPositions as $position) {
            $factories[$position] = array();
        }

        foreach (array_keys($tags) as $tag) {
            $factory = $c->get($tag);
            $factories[$factory->getPosition()][] = $factory;
        }

        return $this->factories = $factories;
    }


    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/security';
    }
}

