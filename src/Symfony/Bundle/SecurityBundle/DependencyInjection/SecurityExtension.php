<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Configuration\Processor;
use Symfony\Component\DependencyInjection\Configuration\Builder\TreeBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\RequestMatcher;

/**
 * SecurityExtension.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SecurityExtension extends Extension
{
    protected $requestMatchers = array();
    protected $contextListeners = array();
    protected $listenerPositions = array('pre_auth', 'form', 'http', 'remember_me');
    protected $configuration;
    protected $factories;

    public function __construct()
    {
        $this->configuration = new Configuration();
    }

    public function configLoad(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();

        // first assemble the factories
        $factories = $this->createListenerFactories($container, $processor->process($this->configuration->getFactoryConfigTree(), $configs));

        // normalize and merge the actual configuration
        $tree = $this->configuration->getMainConfigTree($factories);
        $config = $processor->process($tree, $configs);

        // load services
        $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $loader->load('security.xml');
        $loader->load('security_listeners.xml');
        $loader->load('security_rememberme.xml');
        $loader->load('templating_php.xml');
        $loader->load('templating_twig.xml');
        $loader->load('collectors.xml');

        // set some global scalars
        if (isset($config['access_denied_url'])) {
            $container->setParameter('security.access.denied_url', $config['access_denied_url']);
        }
        if (isset($config['session_fixation_protection'])) {
            $container->setParameter('security.authentication.session_strategy.strategy', $config['session_fixation_protection']);
        }

        $this->createFirewalls($config, $container);
        $this->createAuthorization($config, $container);
        $this->createRoleHierarchy($config, $container);
    }

    public function aclLoad(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $config = $processor->process($this->configuration->getAclConfigTree(), $configs);

        $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $loader->load('security_acl.xml');

        if (isset($config['connection'])) {
            $container->setAlias('security.acl.dbal.connection', sprintf('doctrine.dbal.%s_connection', $config['connection']));
        }

        if (isset($config['cache'])) {
            $container->setAlias('security.acl.cache', sprintf('security.acl.cache.%s', $config['cache']));
        }
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
        return 'http://www.symfony-project.org/schema/dic/security';
    }

    public function getAlias()
    {
        return 'security';
    }

    /**
     * Loads the web configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */

    protected function createRoleHierarchy($config, ContainerBuilder $container)
    {
        if (!isset($config['role_hierarchy'])) {
            return;
        }

        $container->setParameter('security.role_hierarchy.roles', $config['role_hierarchy']);
        $container->remove('security.access.simple_role_voter');
        $container->getDefinition('security.access.role_hierarchy_voter')->addTag('security.voter');
    }

    protected function createAuthorization($config, ContainerBuilder $container)
    {
        if (!isset($config['access_control'])) {
            return;
        }

        foreach ($config['access_control'] as $access) {
            $matcher = $this->createRequestMatcher(
                $container,
                $access['path'],
                $access['host'],
                count($access['methods']) === 0 ? null : $access['methods'],
                $access['ip'],
                $access['attributes']
            );

            $container->getDefinition('security.access_map')
                      ->addMethodCall('add', array($matcher, $access['roles'], $access['requires_channel']));
        }
    }

    protected function createFirewalls($config, ContainerBuilder $container)
    {
        if (!isset($config['firewalls'])) {
            return;
        }

        $firewalls = $config['firewalls'];
        $providerIds = $this->createUserProviders($config, $container);

        $this->createEncoders($config, $container);

        // make the ContextListener aware of the configured user providers
        $definition = $container->getDefinition('security.context_listener');
        $arguments = $definition->getArguments();
        $userProviders = array();
        foreach ($providerIds as $userProviderId) {
            $userProviders[] = new Reference($userProviderId);
        }
        $arguments[1] = $userProviders;
        $definition->setArguments($arguments);

        // create security listener factories
        $factories = $this->createListenerFactories($container, $config);

        // load firewall map
        $mapDef = $container->getDefinition('security.firewall.map');
        $map = array();
        foreach ($firewalls as $name => $firewall) {
            list($matcher, $listeners, $exceptionListener) = $this->createFirewall($container, $name, $firewall, $providerIds, $factories);

            $contextId = 'security.firewall.map.context.'.$name;
            $context = $container->setDefinition($contextId, new DefinitionDecorator('security.firewall.context'));
            $context
                ->setArgument(0, $listeners)
                ->setArgument(1, $exceptionListener)
            ;
            $map[$contextId] = $matcher;
        }
        $mapDef->setArgument(1, $map);
    }

    protected function createFirewall(ContainerBuilder $container, $id, $firewall, $providerIds, array $factories)
    {
        // Matcher
        $i = 0;
        $matcher = null;
        if (isset($firewall['request_matcher'])) {
            $matcher = new Reference($firewall['request_matcher']);
        } else if (isset($firewall['pattern'])) {
            $matcher = $this->createRequestMatcher($container, $firewall['pattern']);
        }

        // Security disabled?
        if (false === $firewall['security']) {
            return array($matcher, array(), null);
        }

        // Provider id (take the first registered provider if none defined)
        if (isset($firewall['provider'])) {
            $defaultProvider = $this->getUserProviderId($firewall['provider']);
        } else {
            $defaultProvider = reset($providerIds);
        }

        // Register listeners
        $listeners = array();
        $providers = array();

        // Channel listener
        $listeners[] = new Reference('security.channel_listener');

        // Context serializer listener
        if (false === $firewall['stateless']) {
            $contextKey = $id;
            if (isset($firewall['context'])) {
                $contextKey = $firewall['context'];
            }

            $listeners[] = new Reference($this->createContextListener($container, $contextKey));
        }

        // Logout listener
        if (isset($firewall['logout'])) {
            $listenerId = 'security.logout_listener.'.$id;
            $listener = $container->setDefinition($listenerId, new DefinitionDecorator('security.logout_listener'));
            $listener->addArgument($firewall['logout']['path']);
            $listener->addArgument($firewall['logout']['target']);
            $listeners[] = new Reference($listenerId);

            // add session logout handler
            if (true === $firewall['logout']['invalidate_session'] && false === $firewall['stateless']) {
                $listener->addMethodCall('addHandler', array(new Reference('security.logout.handler.session')));
            }

            // add cookie logout handler
            if (count($firewall['logout']['delete_cookies']) > 0) {
                $cookieHandlerId = 'security.logout.handler.cookie_clearing.'.$id;
                $cookieHandler = $container->setDefinition($cookieHandlerId, new DefinitionDecorator('security.logout.handler.cookie_clearing'));
                $cookieHandler->addArgument($firewall['logout']['delete_cookies']);

                $listener->addMethodCall('addHandler', array(new Reference($cookieHandlerId)));
            }

            // add custom handlers
            foreach ($firewall['logout']['handlers'] as $handlerId) {
                $listener->addMethodCall('addHandler', array(new Reference($handlerId)));
            }
        }

        // Authentication listeners
        list($authListeners, $providers, $defaultEntryPoint) = $this->createAuthenticationListeners($container, $id, $firewall, $defaultProvider, $factories);

        $listeners = array_merge($listeners, $authListeners);

        // Access listener
        $listeners[] = new Reference('security.access_listener');

        // Switch user listener
        if (isset($firewall['switch_user'])) {
            $listeners[] = new Reference($this->createSwitchUserListener($container, $id, $firewall['switch_user'], $defaultProvider));
        }

        // Determine default entry point
        if (isset($firewall['entry_point'])) {
            $defaultEntryPoint = $firewall['entry_point'];
        }

        // Exception listener
        $exceptionListener = new Reference($this->createExceptionListener($container, $firewall, $id, $defaultEntryPoint));

        return array($matcher, $listeners, $exceptionListener);
    }

    protected function createContextListener($container, $contextKey)
    {
        if (isset($this->contextListeners[$contextKey])) {
            return $this->contextListeners[$contextKey];
        }

        $listenerId = 'security.context_listener.'.count($this->contextListeners);
        $listener = $container->setDefinition($listenerId, new DefinitionDecorator('security.context_listener'));
        $listener->setArgument(2, $contextKey);

        return $this->contextListeners[$contextKey] = $listenerId;
    }

    protected function createAuthenticationListeners($container, $id, $firewall, $defaultProvider, array $factories)
    {
        $listeners = array();
        $providers = array();
        $hasListeners = false;
        $defaultEntryPoint = null;

        foreach ($this->listenerPositions as $position) {
            foreach ($factories[$position] as $factory) {
                $key = str_replace('-', '_', $factory->getKey());

                if (isset($firewall[$key])) {
                    $userProvider = isset($firewall[$key]['provider']) ? $this->getUserProviderId($firewall[$key]['provider']) : $defaultProvider;

                    list($provider, $listenerId, $defaultEntryPoint) = $factory->create($container, $id, $firewall[$key], $userProvider, $defaultEntryPoint);

                    $listeners[] = new Reference($listenerId);
                    $providers[] = new Reference($provider);
                    $hasListeners = true;
                }
            }
        }

        // Anonymous
        if (isset($firewall['anonymous'])) {
            $listeners[] = new Reference('security.authentication.listener.anonymous');
            $hasListeners = true;
        }

        if (false === $hasListeners) {
            throw new \LogicException(sprintf('No authentication listener registered for pattern "%s".', isset($firewall['pattern']) ? $firewall['pattern'] : ''));
        }

        return array($listeners, $providers, $defaultEntryPoint);
    }

    protected function createEncoders($config, ContainerBuilder $container)
    {
        if (!isset($config['encoders'])) {
            return;
        }

        $encoderMap = array();
        foreach ($config['encoders'] as $class => $encoder) {
            $encoderMap = $this->createEncoder($encoderMap, $class, $encoder, $container);
        }

        $container
            ->getDefinition('security.encoder_factory.generic')
            ->setArguments(array($encoderMap))
        ;
    }

    protected function createEncoder(array $encoderMap, $accountClass, $config, ContainerBuilder $container)
    {
        // a custom encoder service
        if (isset($config['id'])) {
            $container
                ->getDefinition('security.encoder_factory.generic')
                ->addMethodCall('addEncoder', array($accountClass, new Reference($config['id'])))
            ;

            return $encoderMap;
        }

        // plaintext encoder
        if ('plaintext' === $config['algorithm']) {
            $arguments = array();

            if (isset($config['ignore_case'])) {
                $arguments[0] = $config['ignore_case'];
            }

            $encoderMap[$accountClass] = array(
                'class' => new Parameter('security.encoder.plain.class'),
                'arguments' => $arguments,
            );

            return $encoderMap;
        }

        // message digest encoder
        $arguments = array($config['algorithm']);

        // add optional arguments
        if (isset($config['encode_as_base64'])) {
            $arguments[1] = $config['encode_as_base64'];
        } else {
            $arguments[1] = false;
        }

        if (isset($config['iterations'])) {
            $arguments[2] = $config['iterations'];
        } else {
            $arguments[2] = 1;
        }

        $encoderMap[$accountClass] = array(
            'class' => new Parameter('security.encoder.digest.class'),
            'arguments' => $arguments,
        );

        return $encoderMap;
    }

    // Parses user providers and returns an array of their ids
    protected function createUserProviders($config, ContainerBuilder $container)
    {
        $providerIds = array();
        foreach ($config['providers'] as $name => $provider) {
            $id = $this->createUserDaoProvider($name, $provider, $container);
            $providerIds[] = $id;
        }

        return $providerIds;
    }

    // Parses a <provider> tag and returns the id for the related user provider service
    // FIXME: Replace register() calls in this method with DefinitionDecorator
    //        and move the actual definition to an xml file
    protected function createUserDaoProvider($name, $provider, ContainerBuilder $container, $master = true)
    {
        $name = $this->getUserProviderId(strtolower($name));

        // Existing DAO service provider
        if (isset($provider['id'])) {
            $container->setAlias($name, new Alias($provider['id'], false));

            return $provider['id'];
        }

        // Chain provider
        if (count($provider['providers']) > 0) {
            // FIXME
            throw new \RuntimeException('Not implemented yet.');
        }

        // Doctrine Entity DAO provider
        if (isset($provider['entity'])) {
            $container
                ->register($name, '%security.user.provider.entity.class%')
                ->setPublic(false)
                ->setArguments(array(
                    new Reference('security.user.entity_manager'),
                    $provider['entity']['class'],
                    $provider['entity']['property'],
                ))
            ;

            return $name;
        }

        // Doctrine Document DAO provider
        if (isset($provider['document'])) {
            $container
                ->register($name, '%security.user.provider.document.class%')
                ->setPublic(false)
                ->setArguments(array(
                    new Reference('security.user.document_manager'),
                    $provider['document']['class'],
                    $provider['document']['property'],
            ));

            return $name;
        }

        // In-memory DAO provider
        $definition = $container->register($name, '%security.user.provider.in_memory.class%');
        $definition->setPublic(false);
        foreach ($provider['users'] as $username => $user) {
            $userId = $name.'_'.md5(json_encode(array($username, $user['password'], $user['roles'])));

            $container
                ->register($userId, 'Symfony\Component\Security\Core\User\User')
                ->setArguments(array($username, $user['password'], $user['roles']))
                ->setPublic(false)
            ;

            $definition->addMethodCall('createUser', array(new Reference($userId)));
        }

        return $name;
    }

    protected function getUserProviderId($name)
    {
        return 'security.authentication.provider.'.$name;
    }

    protected function createExceptionListener($container, $config, $id, $defaultEntryPoint)
    {
        $exceptionListenerId = 'security.exception_listener.'.$id;
        $listener = $container->setDefinition($exceptionListenerId, new DefinitionDecorator('security.exception_listener'));
        $listener->setArgument(2, null === $defaultEntryPoint ? null : new Reference($defaultEntryPoint));

        // access denied handler setup
        if (isset($config['access_denied_handler'])) {
            $listener->setArgument(4, new Reference($config['access_denied_handler']));
        } else if (isset($config['access_denied_url'])) {
            $listener->setArgument(3, $config['access_denied_url']);
        }

        return $exceptionListenerId;
    }

    protected function createSwitchUserListener($container, $id, $config, $defaultProvider)
    {
        $userProvider = isset($config['provider']) ? $this->getUserProviderId($config['provider']) : $defaultProvider;

        $switchUserListenerId = 'security.authentication.switchuser_listener.'.$id;
        $listener = $container->setDefinition($switchUserListenerId, new DefinitionDecorator('security.authentication.switchuser_listener'));
        $listener->setArgument(1, new Reference($userProvider));
        $listener->setArgument(3, $id);
        $listener->addArgument($config['parameter']);
        $listener->addArgument($config['role']);

        return $switchUserListenerId;
    }

    protected function createRequestMatcher($container, $path = null, $host = null, $methods = null, $ip = null, array $attributes = array())
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

    protected function createListenerFactories(ContainerBuilder $container, $config)
    {
        if (null !== $this->factories) {
            return $this->factories;
        }

        // load service templates
        $c = new ContainerBuilder();
        $parameterBag = $container->getParameterBag();
        $loader = new XmlFileLoader($c, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $loader->load('security_factories.xml');

        // load user-created listener factories
        foreach ($config['factories'] as $factory) {
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
}
