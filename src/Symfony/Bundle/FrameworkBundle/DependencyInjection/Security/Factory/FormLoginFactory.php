<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * FormLoginFactory creates services for form login authentication.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FormLoginFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $provider = 'security.authentication.provider.dao.'.$id;
        $container
            ->register($provider, '%security.authentication.provider.dao.class%')
            ->setArguments(array(new Reference($userProvider), new Reference('security.account_checker'), $id, new Reference('security.encoder_factory')))
            ->setPublic(false)
            ->addTag('security.authentication_provider')
        ;

        // listener
        $listenerId = 'security.authentication.listener.form.'.$id;
        $listener = $container->setDefinition($listenerId, clone $container->getDefinition('security.authentication.listener.form'));
        $listener->setArgument(3, $id);

        // add remember-me tag
        $rememberMe = true;
        if (isset($config['remember-me']) && false === $config['remember-me']) {
            $rememberMe = false;
        } else if (isset($config['remember_me']) && false === $config['remember_me']) {
            $rememberMe = false;
        }
        if ($rememberMe) {
            $listener->addTag('security.remember_me_aware', array('id' => $id, 'provider' => $userProvider));
        }

        // generate options
        $options = array(
            'check_path'                     => '/login_check',
            'login_path'                     => '/login',
            'use_forward'                    => false,
            'always_use_default_target_path' => false,
            'default_target_path'            => '/',
            'target_path_parameter'          => '_target_path',
            'use_referer'                    => false,
            'failure_path'                   => null,
            'failure_forward'                => false,
        );
        foreach (array_keys($options) as $key) {
            if (isset($config[$key])) {
                $options[$key] = $config[$key];
            }
        }
        $listener->setArgument(4, $options);

        // success handler
        if (isset($config['success_handler'])) {
            $config['success-handler'] = $config['success_handler'];
        }
        if (isset($config['success-handler'])) {
            $listener->setArgument(5, new Reference($config['success-handler']));
        }

        // failure handler
        if (isset($config['failure_handler'])) {
            $config['failure-handler'] = $config['failure_handler'];
        }
        if (isset($config['failure-handler'])) {
            $listener->setArgument(6, new Reference($config['failure-handler']));
        }

        // form entry point
        $entryPoint = $container->setDefinition($entryPointId = 'security.authentication.form_entry_point.'.$id, clone $container->getDefinition('security.authentication.form_entry_point'));
        $entryPoint->setArguments(array($options['login_path'], $options['use_forward']));

        return array($provider, $listenerId, $entryPointId);
    }

    public function getPosition()
    {
        return 'form';
    }

    public function getKey()
    {
        return 'form-login';
    }
}
