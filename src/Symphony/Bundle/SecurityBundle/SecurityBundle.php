<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle;

use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\JsonLoginFactory;
use Symphony\Component\Console\Application;
use Symphony\Component\HttpKernel\Bundle\Bundle;
use Symphony\Component\DependencyInjection\Compiler\PassConfig;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSecurityVotersPass;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSessionDomainConstraintPass;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginFactory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginLdapFactory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\HttpBasicFactory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\HttpBasicLdapFactory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\RememberMeFactory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\X509Factory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\RemoteUserFactory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SimplePreAuthenticationFactory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SimpleFormFactory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\InMemoryFactory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\GuardAuthenticationFactory;
use Symphony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\LdapFactory;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class SecurityBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new FormLoginFactory());
        $extension->addSecurityListenerFactory(new FormLoginLdapFactory());
        $extension->addSecurityListenerFactory(new JsonLoginFactory());
        $extension->addSecurityListenerFactory(new HttpBasicFactory());
        $extension->addSecurityListenerFactory(new HttpBasicLdapFactory());
        $extension->addSecurityListenerFactory(new RememberMeFactory());
        $extension->addSecurityListenerFactory(new X509Factory());
        $extension->addSecurityListenerFactory(new RemoteUserFactory());
        $extension->addSecurityListenerFactory(new SimplePreAuthenticationFactory());
        $extension->addSecurityListenerFactory(new SimpleFormFactory());
        $extension->addSecurityListenerFactory(new GuardAuthenticationFactory());

        $extension->addUserProviderFactory(new InMemoryFactory());
        $extension->addUserProviderFactory(new LdapFactory());
        $container->addCompilerPass(new AddSecurityVotersPass());
        $container->addCompilerPass(new AddSessionDomainConstraintPass(), PassConfig::TYPE_AFTER_REMOVING);
    }

    public function registerCommands(Application $application)
    {
        // noop
    }
}
