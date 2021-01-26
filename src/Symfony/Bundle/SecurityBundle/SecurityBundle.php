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

use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddExpressionLanguageProvidersPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSecurityVotersPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddSessionDomainConstraintPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterCsrfFeaturesPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterEntryPointPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterGlobalSecurityEventListenersPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterLdapLocatorPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterTokenUsageTrackingPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\SortFirewallListenersPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AnonymousFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\CustomAuthenticatorFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginLdapFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\GuardAuthenticationFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\HttpBasicFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\HttpBasicLdapFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\JsonLoginFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\JsonLoginLdapFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\LoginLinkFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\LoginThrottlingFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\RememberMeFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\RemoteUserFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\X509Factory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\InMemoryFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\LdapFactory;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\DependencyInjection\AddEventAliasesPass;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Http\SecurityEvents;

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
        $extension->addSecurityListenerFactory(new JsonLoginFactory());
        $extension->addSecurityListenerFactory(new JsonLoginLdapFactory());
        $extension->addSecurityListenerFactory(new HttpBasicFactory());
        $extension->addSecurityListenerFactory(new HttpBasicLdapFactory());
        $extension->addSecurityListenerFactory(new RememberMeFactory());
        $extension->addSecurityListenerFactory(new X509Factory());
        $extension->addSecurityListenerFactory(new RemoteUserFactory());
        $extension->addSecurityListenerFactory(new GuardAuthenticationFactory());
        $extension->addSecurityListenerFactory(new AnonymousFactory());
        $extension->addSecurityListenerFactory(new CustomAuthenticatorFactory());
        $extension->addSecurityListenerFactory(new LoginThrottlingFactory());
        $extension->addSecurityListenerFactory(new LoginLinkFactory());

        $extension->addUserProviderFactory(new InMemoryFactory());
        $extension->addUserProviderFactory(new LdapFactory());
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());
        $container->addCompilerPass(new AddSecurityVotersPass());
        $container->addCompilerPass(new AddSessionDomainConstraintPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new RegisterCsrfFeaturesPass());
        $container->addCompilerPass(new RegisterTokenUsageTrackingPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 200);
        $container->addCompilerPass(new RegisterLdapLocatorPass());
        $container->addCompilerPass(new RegisterEntryPointPass());
        // must be registered after RegisterListenersPass (in the FrameworkBundle)
        $container->addCompilerPass(new RegisterGlobalSecurityEventListenersPass(), PassConfig::TYPE_BEFORE_REMOVING, -200);
        // execute after ResolveChildDefinitionsPass optimization pass, to ensure class names are set
        $container->addCompilerPass(new SortFirewallListenersPass(), PassConfig::TYPE_BEFORE_REMOVING);

        $container->addCompilerPass(new AddEventAliasesPass(array_merge(
            AuthenticationEvents::ALIASES,
            SecurityEvents::ALIASES
        )));
    }
}
