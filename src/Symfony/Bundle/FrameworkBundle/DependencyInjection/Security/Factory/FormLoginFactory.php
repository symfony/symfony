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
            ->setArguments(array(new Reference($userProvider), new Reference('security.account_checker'), new Reference('security.encoder_factory')))
            ->setPublic(false)
        ;

        // listener
        $listenerId = 'security.authentication.listener.form.'.$id;
        $listener = $container->setDefinition($listenerId, clone $container->getDefinition('security.authentication.listener.form'));
        $arguments = $listener->getArguments();
        $arguments[1] = new Reference($provider);
        $listener->setArguments($arguments);

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
        $container->setParameter('security.authentication.form.options', $options);
        $container->setParameter('security.authentication.form.login_path', $options['login_path']);
        $container->setParameter('security.authentication.form.use_forward', $options['use_forward']);

        return array($provider, $listenerId, 'security.authentication.form_entry_point');
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
