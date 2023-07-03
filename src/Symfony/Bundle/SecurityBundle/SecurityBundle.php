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
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\CleanRememberMeVerifierPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\MakeFirewallsEventDispatcherTraceablePass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterCsrfFeaturesPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterEntryPointPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterGlobalSecurityEventListenersPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterLdapLocatorPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterTokenUsageTrackingPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\ReplaceDecoratedRememberMeHandlerPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\SortFirewallListenersPass;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\OidcTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\OidcUserInfoTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken\ServiceTokenHandlerFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AccessTokenFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\CustomAuthenticatorFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\FormLoginLdapFactory;
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
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
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
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');
        $extension->addAuthenticatorFactory(new FormLoginFactory());
        $extension->addAuthenticatorFactory(new FormLoginLdapFactory());
        $extension->addAuthenticatorFactory(new JsonLoginFactory());
        $extension->addAuthenticatorFactory(new JsonLoginLdapFactory());
        $extension->addAuthenticatorFactory(new HttpBasicFactory());
        $extension->addAuthenticatorFactory(new HttpBasicLdapFactory());
        $extension->addAuthenticatorFactory(new RememberMeFactory());
        $extension->addAuthenticatorFactory(new X509Factory());
        $extension->addAuthenticatorFactory(new RemoteUserFactory());
        $extension->addAuthenticatorFactory(new CustomAuthenticatorFactory());
        $extension->addAuthenticatorFactory(new LoginThrottlingFactory());
        $extension->addAuthenticatorFactory(new LoginLinkFactory());
        $extension->addAuthenticatorFactory(new AccessTokenFactory([
            new ServiceTokenHandlerFactory(),
            new OidcUserInfoTokenHandlerFactory(),
            new OidcTokenHandlerFactory(),
        ]));

        $extension->addUserProviderFactory(new InMemoryFactory());
        $extension->addUserProviderFactory(new LdapFactory());
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());
        $container->addCompilerPass(new AddSecurityVotersPass());
        $container->addCompilerPass(new AddSessionDomainConstraintPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new CleanRememberMeVerifierPass());
        $container->addCompilerPass(new RegisterCsrfFeaturesPass());
        $container->addCompilerPass(new RegisterTokenUsageTrackingPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 200);
        $container->addCompilerPass(new RegisterLdapLocatorPass());
        $container->addCompilerPass(new RegisterEntryPointPass());
        // must be registered after RegisterListenersPass (in the FrameworkBundle)
        $container->addCompilerPass(new RegisterGlobalSecurityEventListenersPass(), PassConfig::TYPE_BEFORE_REMOVING, -200);
        // execute after ResolveChildDefinitionsPass optimization pass, to ensure class names are set
        $container->addCompilerPass(new SortFirewallListenersPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new ReplaceDecoratedRememberMeHandlerPass(), PassConfig::TYPE_OPTIMIZE);

        $container->addCompilerPass(new AddEventAliasesPass(array_merge(
            AuthenticationEvents::ALIASES,
            SecurityEvents::ALIASES
        )));

        // must be registered before DecoratorServicePass
        $container->addCompilerPass(new MakeFirewallsEventDispatcherTraceablePass(), PassConfig::TYPE_OPTIMIZE, 10);
    }
}
