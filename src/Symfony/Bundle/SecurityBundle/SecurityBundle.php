<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSecurityVotersPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginLdapFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\HttpBasicFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\HttpBasicLdapFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\HttpDigestFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\RememberMeFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\X509Factory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\InMemoryFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\LdapFactory;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new FormLoginFactory());
        $extension->addSecurityListenerFactory(new FormLoginLdapFactory());
        $extension->addSecurityListenerFactory(new HttpBasicFactory());
        $extension->addSecurityListenerFactory(new HttpBasicLdapFactory());
        $extension->addSecurityListenerFactory(new HttpDigestFactory());
        $extension->addSecurityListenerFactory(new RememberMeFactory());
        $extension->addSecurityListenerFactory(new X509Factory());

        $extension->addUserProviderFactory(new InMemoryFactory());
        $extension->addUserProviderFactory(new LdapFactory());
        $container->addCompilerPass(new AddSecurityVotersPass());
    }
}
