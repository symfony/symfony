<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\RequestMatcher;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * SecurityExtension.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SecurityExtension extends Extension
{
    /**
     * Loads the web configuration.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function configLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.context')) {
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('security.xml');
        }

        if (isset($config['access-denied-url'])) {
            $container->setParameter('security.access_denied.url', $config['access-denied-url']);
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
        } elseif (isset($config['role-hierarchy'])) {
            $roles = $config['role-hierarchy'];
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
        } elseif (isset($config['access-control'])) {
            $rules = $config['access-control'];
        }

        if (isset($rules['rule']) && is_array($rules['rule'])) {
            $rules = $rules['rule'];
        }

        foreach ($rules as $i => $access) {
            $roles = isset($access['role']) ? (is_array($access['role']) ? $access['role'] : preg_split('/\s*,\s*/', $access['role'])) : array();
            $channel = null;
            if (isset($access['requires-channel'])) {
                $channel = $access['requires-channel'];
            } elseif (isset($access['requires_channel'])) {
                $channel = $access['requires_channel'];
            }

            // matcher
            $id = 'security.matcher.url.'.$i;
            $definition = $container->register($id, '%security.matcher.class%');
            if (isset($access['path'])) {
                $definition->addMethodCall('matchPath', array(is_array($access['path']) ? $access['path']['pattern'] : $access['path']));
            }

            $attributes = $this->fixConfig($access, 'attribute');
            foreach ($attributes as $key => $attribute) {
                if (isset($attribute['key'])) {
                    $key = $attribute['key'];
                }
                $definition->addMethodCall('matchAttribute', array($key, $attribute['pattern']));
            }

            $container->getDefinition('security.access_map')->addMethodCall('add', array(new Reference($id), $roles, $channel));
        }
    }

    protected function createFirewalls($config, ContainerBuilder $container)
    {
        $providerIds = $this->createAuthenticationProviders($config, $container);

        if (!$firewalls = $this->fixConfig($config, 'firewall')) {
            return;
        }

        // load service templates
        $c = new ContainerBuilder($container->getParameterBag());
        $loader = new XmlFileLoader($c, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $loader->load('security_templates.xml');

        foreach ($this->fixConfig($config, 'template') as $template) {
            $loader->load($c->getParameterBag()->resolveValue($template));
        }
        $container->merge($c);

        // load firewall map
        $map = $container->getDefinition('security.firewall.map');
        foreach ($firewalls as $firewall) {
            list($matcher, $listeners, $exceptionListener) = $this->createFirewall($container, $firewall, $providerIds);

            $map->addMethodCall('add', array($matcher, $listeners, $exceptionListener));
        }

        // remove all service templates
        foreach ($c->getServiceIds() as $id) {
            $container->remove($id);
        }
    }

    protected function createFirewall(ContainerBuilder $container, $firewall, $providerIds)
    {
        // unique id for this firewall
        $id = md5(serialize($firewall));

        // Matcher
        $i = 0;
        $matcher = null;
        if (isset($firewall['pattern'])) {
            $id = 'security.matcher.map'.$id.'.'.++$i;
            $matcher = $container
                ->register($id, '%security.matcher.class%')
                ->addMethodCall('matchPath', array($firewall['pattern']))
            ;
            $matcher = new Reference($id);
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
            $keys = array_keys($providerIds);
            $defaultProvider = current($keys);
        }

        // Register listeners
        $listeners = array();
        $providers = array();

        // Channel listener
        $listeners[] = new Reference('security.channel_listener');

        // Context serializer listener
        if (!isset($firewall['stateless']) || !$firewall['stateless']) {
            $listeners[] = new Reference('security.context_listener');
        }

        // Logout listener
        if (array_key_exists('logout', $firewall)) {
            $listeners[] = new Reference('security.logout_listener');

            if (isset($firewall['logout']['path'])) {
                $container->setParameter('security.logout.path', $firewall['logout']['path']);
            }

            if (isset($firewall['logout']['target'])) {
                $container->setParameter('security.logout.target_path', $firewall['logout']['target']);
            }
        }

        // Authentication listeners
        list($authListeners, $providers, $defaultEntryPoint) = $this->createAuthenticationListeners($container, $id, $firewall, $defaultProvider, $providerIds);

        $listeners = array_merge($listeners, $authListeners);

        // Access listener
        $listeners[] = new Reference($this->createAccessListener($container, $id, $providers));

        // Switch user listener
        if (array_key_exists('switch_user', $firewall)) {
            $firewall['switch-user'] = $firewall['switch_user'];
        }
        if (array_key_exists('switch-user', $firewall)) {
            $listeners[] = new Reference($this->createSwitchUserListener($container, $id, $firewall['switch-user'], $defaultProvider));
        }

        // Exception listener
        $exceptionListener = new Reference($this->createExceptionListener($container, $id, $defaultEntryPoint));

        return array($matcher, $listeners, $exceptionListener);
    }

    protected function createAuthenticationListeners($container, $id, $firewall, $defaultProvider, $providerIds)
    {
        $listeners = array();
        $providers = array();
        $hasListeners = false;
        $defaultEntryPoint = null;

        $positions = array('pre_auth', 'form', 'http');

        $tags = $container->findTaggedServiceIds('security.listener.factory');
        $factories = array();
        foreach ($positions as $position) {
            $factories[$position] = array();
        }

        foreach (array_keys($tags) as $tag) {
            $factory = $container->get($tag);

            $factories[$factory->getPosition()][] = $factory;
        }

        foreach ($positions as $position) {
            foreach ($factories[$position] as $factory) {
                $key = $factory->getKey();
                $keybis = str_replace('-', '_', $key);

                if (array_key_exists($keybis, $firewall)) {
                    $firewall[$key] = $firewall[$keybis];
                }
                if (array_key_exists($key, $firewall)) {
                    $userProvider = isset($firewall[$key]['provider']) ? $this->getUserProviderId($firewall[$key]['provider']) : $defaultProvider;

                    list($provider, $listener, $defaultEntryPoint) = $factory->create($container, $id, $firewall[$key], $userProvider, $providerIds, $defaultEntryPoint);

                    $listeners[] = new Reference($listener);
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
    protected function createAuthenticationProviders($config, ContainerBuilder $container)
    {
        $providers = $this->fixConfig($config, 'provider');
        if (!$providers) {
            return array();
        }

        $providerIds = array();
        foreach ($providers as $name => $provider) {
            list($id, $encoder) = $this->createUserDaoProvider($name, $provider, $container);
            $providerIds[$id] = $encoder;
        }

        return $providerIds;
    }

    // Parses a <provider> tag and returns the id for the related user provider service
    protected function createUserDaoProvider($name, $provider, ContainerBuilder $container, $master = true)
    {
        // encoder
        $encoder = 'plain';
        if (isset($provider['password-encoder'])) {
            $encoder = $provider['password-encoder'];
        } elseif (isset($provider['password_encoder'])) {
            $encoder = $provider['password_encoder'];
        }

        if (isset($provider['name'])) {
            $name = $provider['name'];
        }

        if (!$name) {
            $name = md5(serialize($provider));
        }

        $name = $this->getUserProviderId($name);

        // Existing DAO service provider
        if (isset($provider['id'])) {
            $container->setAlias($name, $provider['id']);

            return array($name, $encoder);
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
                ->setArguments(array(
                    new Reference('security.user.entity_manager'),
                    $provider['entity']['class'],
                    isset($provider['entity']['property']) ? $provider['entity']['property'] : null,
            ));

            return array($name, $encoder);
        }

        // Doctrine Document DAO provider
        if (isset($provider['document'])) {
            $container
                ->register($name, '%security.user.provider.document.class%')
                ->setArguments(array(
                    new Reference('security.user.document_manager'),
                    $provider['document']['class'],
                    isset($provider['document']['property']) ? $provider['document']['property'] : null,
            ));

            return array($name, $encoder);
        }

        // In-memory DAO provider
        $definition = $container->register($name, '%security.user.provider.in_memory.class%');
        foreach ($this->fixConfig($provider, 'user') as $username => $user) {
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
                ->register($userId, 'Symfony\Component\Security\User\User')
                ->setArguments(array($username, $user['password'], $user['roles']))
            ;

            $definition->addMethodCall('createUser', array(new Reference($userId)));
        }

        return array($name, $encoder);
    }

    protected function getUserProviderId($name)
    {
        return 'security.authentication.provider.'.$name;
    }

    protected function createAccessListener($container, $id, $providers)
    {
        // Authentication manager
        $authManager = 'security.authentication.manager.'.$id;
        $container
            ->register($authManager, '%security.authentication.manager.class%')
            ->addArgument($providers)
        ;

        // Access listener
        $listenerId = 'security.access_listener.'.$id;
        $listener = $container->setDefinition($listenerId, clone $container->getDefinition('security.access_listener'));
        $arguments = $listener->getArguments();
        $arguments[3] = new Reference($authManager);
        $listener->setArguments($arguments);

        return $listenerId;
    }

    protected function createExceptionListener($container, $id, $defaultEntryPoint)
    {
        $exceptionListenerId = 'security.exception_listener.'.$id;
        $listener = $container->setDefinition($exceptionListenerId, clone $container->getDefinition('security.exception_listener'));
        $arguments = $listener->getArguments();
        $arguments[1] = null === $defaultEntryPoint ? null : new Reference($defaultEntryPoint);
        $listener->setArguments($arguments);

        return $exceptionListenerId;
    }

    protected function createSwitchUserListener($container, $id, $config, $defaultProvider)
    {
        $userProvider = isset($config['provider']) ? $this->getUserProviderId($config['provider']) : $defaultProvider;

        $switchUserListenerId = 'security.authentication.switchuser_listener.'.$id;
        $listener = $container->setDefinition($switchUserListenerId, clone $container->getDefinition('security.authentication.switchuser_listener'));
        $arguments = $listener->getArguments();
        $arguments[1] = new Reference($userProvider);
        $listener->setArguments($arguments);

        if (isset($config['role'])) {
            $container->setParameter('security.authentication.switchuser.role', $config['role']);
        }

        if (isset($config['parameter'])) {
            $container->setParameter('security.authentication.switchuser.parameter', $config['parameter']);
        }

        return $switchUserListenerId;
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

    protected function fixConfig($config, $key)
    {
        $values = array();
        if (isset($config[$key.'s'])) {
            $values = $config[$key.'s'];
        } elseif (isset($config[$key])) {
            if (is_string($config[$key]) || !is_int(key($config[$key]))) {
                // only one
                $values = array($config[$key]);
            } else {
                $values = $config[$key];
            }
        }

        return $values;
    }
}
