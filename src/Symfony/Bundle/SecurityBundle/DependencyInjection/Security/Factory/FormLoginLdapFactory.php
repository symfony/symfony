<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * FormLoginLdapFactory creates services for form login ldap authentication.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class FormLoginLdapFactory extends FormLoginFactory
{
    protected function createAuthProvider(ContainerBuilder $container, $id, $config, $userProviderId)
    {
        $provider = 'security.authentication.provider.ldap.'.$id;
        $container
            ->setDefinition($provider, new DefinitionDecorator('security.authentication.provider.ldap'))
            ->replaceArgument(0, new Reference($userProviderId))
            ->replaceArgument(2, $id)
        ;

        return $provider;
    }

    public function getKey()
    {
        return 'form-login-ldap';
    }
}
