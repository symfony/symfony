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

    public function configLoad(array $configs, ContainerBuilder $container)
    {
        foreach ($configs as $config) {
            $this->doConfigLoad($this->normalizeKeys($config), $container);
        }
    }

    public function aclLoad(array $configs, ContainerBuilder $container)
    {
        foreach ($configs as $config) {
            $this->doAclLoad($this->normalizeKeys($config), $container);
        }
    }

    /**
     * Loads the web configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function doConfigLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.context')) {
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('security.xml');
            $loader->load('security_listeners.xml');
            $loader->load('security_rememberme.xml');
            $loader->load('templating_php.xml');
            $loader->load('templating_twig.xml');
            $loader->load('collectors.xml');
        }

        if (isset($config['access_denied_url'])) {
            $container->setParameter('security.access.denied_url', $config['access_denied_url']);
        }

        // session fixation protection
        if (isset($config['session_fixation_protection'])) {
            $container->setParameter('security.authentication.session_strategy.strategy', $config['session_fixation_protection']);
        }

        $this->createFirewalls($config, $container);
        $this->createAuthorization($config, $container);
        $this->createRoleHierarchy($config, $container);

        return $container;
    }

    protected function createRoleHierarchy($config, ContainerBuilder $container)
    {
        $roles = array();
        if (isset($config['role_hierarchy'])) {
            $roles = $config['role_hierarchy'];
        }

        if (isset($roles['role']) && is_int(key($roles['role']))) {
            $roles = $roles['role'];
        }

        $hierarchy = array();
        foreach ($roles as $id => $role) {
            if (is_array($role) && isset($role['id'])) {
                $id = $role['id'];
            }

            $value = $role;
            if (is_array($role) && isset($role['value'])) {
                $value = $role['value'];
            }

            $hierarchy[$id] = is_array($value) ? $value : preg_split('/\s*,\s*/', $value);
        }

        $container->setParameter('security.role_hierarchy.roles', $hierarchy);
        $container->remove('security.access.simple_role_voter');
        $container->getDefinition('security.access.role_hierarchy_voter')->addTag('security.voter');
    }

    protected function createAuthorization($config, ContainerBuilder $container)
    {
        $rules = array();
        if (isset($config['access_control'])) {
            $rules = $config['access_control'];
        }

        if (isset($rules['rule']) && is_array($rules['rule'])) {
            $rules = $rules['rule'];
        }

        foreach ($rules as $i => $access) {
            $roles = isset($access['role']) ? (is_array($access['role']) ? $access['role'] : preg_split('/\s*,\s*/', $access['role'])) : array();
            $channel = null;
            if (isset($access['requires_channel'])) {
                $channel = $access['requires_channel'];
            }

            // matcher
            $path = $host = $methods = $ip = null;
            if (isset($access['path'])) {
                $path = $access['path'];
            }
            if (isset($access['host'])) {
                $host = $access['host'];
            }
            if (count($tMethods = $this->normalizeConfig($access, 'method')) > 0) {
                $methods = $tMethods;
            }
            if (isset($access['ip'])) {
                $ip = $access['ip'];
            }

            $matchAttributes = array();
            $attributes = $this->normalizeConfig($access, 'attribute');
            foreach ($attributes as $key => $attribute) {
                if (isset($attribute['key'])) {
                    $key = $attribute['key'];
                }
                $matchAttributes[$key] = $attribute['pattern'];
            }
            $matcher = $this->createRequestMatcher($container, $path, $host, $methods, $ip, $matchAttributes);

            $container->getDefinition('security.access_map')->addMethodCall('add', array($matcher, $roles, $channel));
        }
    }

    protected function createFirewalls($config, ContainerBuilder $container)
    {
        $providerIds = $this->createUserProviders($config, $container);

        $this->createEncoders($config, $container);

        if (!$firewalls = $this->normalizeConfig($config, 'firewall')) {
            return;
        }

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
        $names = $map = array();
        foreach ($firewalls as $name => $firewall) {
            if (isset($firewall['name'])) {
                $name = $firewall['name'];
            }
            if (in_array($name, $names)) {
                throw new \RuntimeException(sprintf('The firewall name must be unique. Duplicate found: "%s"', $name));
            }
            $names[] = $name;

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
        if (isset($firewall['security']) && !$firewall['security']) {
            return array($matcher, array(), null);
        }

        // Provider id (take the first registered provider if none defined)
        if (isset($firewall['provider'])) {
            $defaultProvider = $this->getUserProviderId($firewall['provider']);
        } else {
            if (!$providerIds) {
                throw new \InvalidArgumentException('You must provide at least one authentication provider.');
            }
            $defaultProvider = reset($providerIds);
        }

        // Register listeners
        $listeners = array();
        $providers = array();

        // Channel listener
        $listeners[] = new Reference('security.channel_listener');

        // Context serializer listener
        if (!isset($firewall['stateless']) || !$firewall['stateless']) {
            $contextKey = $id;
            if (isset($firewall['context'])) {
                $contextKey = $firewall['context'];
            }

            $listeners[] = new Reference($this->createContextListener($container, $contextKey));
        }

        // Logout listener
        if (array_key_exists('logout', $firewall)) {
            $listenerId = 'security.logout_listener.'.$id;
            $listener = $container->setDefinition($listenerId, new DefinitionDecorator('security.logout_listener'));

            $listeners[] = new Reference($listenerId);

            if (!is_array($firewall['logout'])) {
                $firewall['logout'] = array();
            }

            if (isset($firewall['logout']['path'])) {
                $listener->setArgument(1, $firewall['logout']['path']);
            }

            if (isset($firewall['logout']['target'])) {
                $listener->setArgument(2, $firewall['logout']['target']);
            }

            // add session logout handler
            $invalidateSession = true;
            if (isset($firewall['logout']['invalidate_session'])) {
                $invalidateSession = (Boolean) $firewall['logout']['invalidate_session'];
            }
            if (true === $invalidateSession && (!isset($firewall['stateless']) || !$firewall['stateless'])) {
                $listener->addMethodCall('addHandler', array(new Reference('security.logout.handler.session')));
            }

            // add cookie logout handler
            if (count($cookies = $this->normalizeConfig($firewall['logout'], 'cookie')) > 0) {
                $cookieHandlerId = 'security.logout.handler.cookie_clearing.'.$id;
                $cookieHandler = $container->setDefinition($cookieHandlerId, new DefinitionDecorator('security.logout.handler.cookie_clearing'));
                $cookieHandler->addArgument($cookies);

                $listener->addMethodCall('addHandler', array(new Reference($cookieHandlerId)));
            }

            // add custom handlers
            foreach ($this->normalizeConfig($firewall['logout'], 'handler') as $handlerId) {
                $listener->addMethodCall('addHandler', array(new Reference($handlerId)));
            }
        }

        // Authentication listeners
        list($authListeners, $providers, $defaultEntryPoint) = $this->createAuthenticationListeners($container, $id, $firewall, $defaultProvider, $factories);

        $listeners = array_merge($listeners, $authListeners);

        // Access listener
        $listeners[] = new Reference('security.access_listener');

        // Switch user listener
        if (array_key_exists('switch_user', $firewall)) {
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
                $key = $factory->getKey();
                $keybis = str_replace('-', '_', $key);

                if (array_key_exists($keybis, $firewall)) {
                    $firewall[$key] = $firewall[$keybis];
                }
                if (array_key_exists($key, $firewall) && $firewall[$key] !== false) {
                    $userProvider = isset($firewall[$key]['provider']) ? $this->getUserProviderId($firewall[$key]['provider']) : $defaultProvider;

                    list($provider, $listenerId, $defaultEntryPoint) = $factory->create($container, $id, $firewall[$key], $userProvider, $defaultEntryPoint);

                    $listeners[] = new Reference($listenerId);
                    $providers[] = new Reference($provider);
                    $hasListeners = true;
                }
            }
        }

        // Anonymous
        if (array_key_exists('anonymous', $firewall)) {
            $listeners[] = new Reference('security.authentication.listener.anonymous');
            $hasListeners = true;
        }

        if (false === $hasListeners) {
            throw new \LogicException(sprintf('No authentication listener registered for pattern "%s".', isset($firewall['pattern']) ? $firewall['pattern'] : ''));
        }

        return array($listeners, $providers, $defaultEntryPoint);
    }

    // Parses user providers and returns an array of their ids
    protected function createUserProviders($config, ContainerBuilder $container)
    {
        $providers = $this->normalizeConfig($config, 'provider');
        if (!$providers) {
            return array();
        }

        $providerIds = array();
        foreach ($providers as $name => $provider) {
            $id = $this->createUserDaoProvider($name, $provider, $container);

            if (in_array($id, $providerIds, true)) {
                throw new \RuntimeException(sprintf('Provider names must be unique. Duplicate entry for %s.', $id));
            }

            $providerIds[] = $id;
        }

        return $providerIds;
    }

    protected function createListenerFactories(ContainerBuilder $container, $config)
    {
        // load service templates
        $c = new ContainerBuilder();
        $parameterBag = $container->getParameterBag();
        $loader = new XmlFileLoader($c, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $loader->load('security_factories.xml');

        // load user-created listener factories
        foreach ($this->normalizeConfig($config, 'factory', 'factories') as $factory) {
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

        return $factories;
    }

    protected function createEncoders($config, ContainerBuilder $container)
    {
        $encoders = $this->normalizeConfig($config, 'encoder');
        if (!$encoders) {
            return array();
        }

        $encoderMap = array();
        foreach ($encoders as $class => $encoder) {
            $encoderMap = $this->createEncoder($encoderMap, $class, $encoder, $container);
        }

        $container
            ->getDefinition('security.encoder_factory.generic')
            ->setArguments(array($encoderMap))
        ;
    }

    protected function createEncoder(array $encoderMap, $accountClass, $config, ContainerBuilder $container)
    {
        if (is_array($config) && isset($config['class'])) {
            $accountClass = $config['class'];
        }

        if (empty($accountClass)) {
            throw new \RuntimeException('Each encoder needs an account class.');
        }

        // a minimal message digest, or plaintext encoder
        if (is_string($config)) {
            $config = array(
                'algorithm' => $config,
            );
        }

        // a custom encoder service
        if (isset($config['id'])) {
            $container
                ->getDefinition('security.encoder_factory.generic')
                ->addMethodCall('addEncoder', array($accountClass, new Reference($config['id'])))
            ;

            return $encoderMap;
        }

        // a lazy loaded, message digest or plaintext encoder
        if (!isset($config['algorithm'])) {
            throw new \RuntimeException('"algorithm" must be defined.');
        }

        // plaintext encoder
        if ('plaintext' === $config['algorithm']) {
            $arguments = array();

            if (isset($config['ignore_case'])) {
                $arguments[0] = (Boolean) $config['ignore_case'];
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
            $arguments[1] = (Boolean) $config['encode_as_base64'];
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

    // Parses a <provider> tag and returns the id for the related user provider service
    protected function createUserDaoProvider($name, $provider, ContainerBuilder $container, $master = true)
    {
        if (isset($provider['name'])) {
            $name = $provider['name'];
        }

        if (!$name) {
            throw new \RuntimeException('You must define a name for each user provider.');
        }

        $name = $this->getUserProviderId(strtolower($name));

        // Existing DAO service provider
        if (isset($provider['id'])) {
            $container->setAlias($name, new Alias($provider['id'], false));

            return $provider['id'];
        }

        // Chain provider
        if (isset($provider['provider'])) {
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
                    isset($provider['entity']['property']) ? $provider['entity']['property'] : null,
            ));

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
                    isset($provider['document']['property']) ? $provider['document']['property'] : null,
            ));

            return $name;
        }

        // In-memory DAO provider
        $definition = $container->register($name, '%security.user.provider.in_memory.class%');
        $definition->setPublic(false);
        foreach ($this->normalizeConfig($provider, 'user') as $username => $user) {
            if (isset($user['name'])) {
                $username = $user['name'];
            }

            if (!array_key_exists('password', $user)) {
                // if no password is provided explicitly, it means that
                // the user will be used with OpenID, X.509 certificates, ...
                // Let's generate a random password just to be sure this
                // won't be used accidentally with other authentication schemes.
                // If you want an empty password, just say so explicitly
                $user['password'] = uniqid();
            }

            if (!isset($user['roles'])) {
                $user['roles'] = array();
            } else {
                $user['roles'] = is_array($user['roles']) ? $user['roles'] : preg_split('/\s*,\s*/', $user['roles']);
            }

            $userId = $name.'_'.md5(serialize(array($username, $user['password'], $user['roles'])));

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

        if (isset($config['parameter'])) {
            $listener->setArgument(5, $config['parameter']);
        }

        if (isset($config['role'])) {
            $listener->setArgument(6, $config['role']);
        }

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

    protected function doAclLoad(array $config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.acl')) {
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('security_acl.xml');
        }

        if (isset($config['connection'])) {
            $container->setAlias('security.acl.dbal.connection', sprintf('doctrine.dbal.%s_connection', $config['connection']));
        }

        if (isset($config['cache'])) {
            $container->setAlias('security.acl.cache', sprintf('security.acl.cache.%s', $config['cache']));
        } else {
            $container->remove('security.acl.cache.doctrine');
            $container->removeAlias('security.acl.cache.doctrine.cache_impl');
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
}
