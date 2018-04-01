<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symphony\Component\DependencyInjection\ChildDefinition;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;

/**
 * JsonLoginFactory creates services for JSON login authentication.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class JsonLoginFactory extends AbstractFactory
{
    public function __construct()
    {
        $this->addOption('username_path', 'username');
        $this->addOption('password_path', 'password');
        $this->defaultFailureHandlerOptions = array();
        $this->defaultSuccessHandlerOptions = array();
    }

    /**
     * {@inheritdoc}
     */
    public function getPosition()
    {
        return 'form';
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return 'json-login';
    }

    /**
     * {@inheritdoc}
     */
    protected function createAuthProvider(ContainerBuilder $container, $id, $config, $userProviderId)
    {
        $provider = 'security.authentication.provider.dao.'.$id;
        $container
            ->setDefinition($provider, new ChildDefinition('security.authentication.provider.dao'))
            ->replaceArgument(0, new Reference($userProviderId))
            ->replaceArgument(1, new Reference('security.user_checker.'.$id))
            ->replaceArgument(2, $id)
        ;

        return $provider;
    }

    /**
     * {@inheritdoc}
     */
    protected function getListenerId()
    {
        return 'security.authentication.listener.json';
    }

    /**
     * {@inheritdoc}
     */
    protected function isRememberMeAware($config)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function createListener($container, $id, $config, $userProvider)
    {
        $listenerId = $this->getListenerId();
        $listener = new ChildDefinition($listenerId);
        $listener->replaceArgument(3, $id);
        $listener->replaceArgument(4, isset($config['success_handler']) ? new Reference($this->createAuthenticationSuccessHandler($container, $id, $config)) : null);
        $listener->replaceArgument(5, isset($config['failure_handler']) ? new Reference($this->createAuthenticationFailureHandler($container, $id, $config)) : null);
        $listener->replaceArgument(6, array_intersect_key($config, $this->options));

        $listenerId .= '.'.$id;
        $container->setDefinition($listenerId, $listener);

        return $listenerId;
    }
}
