<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bundle\SecurityBundle\CacheWarmer\ExpressionCacheWarmer;
use Symfony\Bundle\SecurityBundle\EventListener\FirewallListener;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Bundle\SecurityBundle\Security\LazyFirewallContext;
use Symfony\Component\Ldap\Security\LdapUserProvider;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\UsageTrackingTokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\ExpressionVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\ChainUserProvider;
use Symfony\Component\Security\Core\User\InMemoryUserChecker;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\MissingUserProvider;
use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Controller\UserValueResolver;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Impersonate\ImpersonateUrlGenerator;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('security.role_hierarchy.roles', [])
    ;

    $container->services()
        ->set('security.authorization_checker', AuthorizationChecker::class)
            ->public()
            ->args([
                service('security.token_storage'),
                service('security.access.decision_manager'),
                param('security.access.always_authenticate_before_granting'),
            ])
            ->tag('container.private', ['package' => 'symfony/security-bundle', 'version' => '5.3'])
        ->alias(AuthorizationCheckerInterface::class, 'security.authorization_checker')

        ->set('security.token_storage', UsageTrackingTokenStorage::class)
            ->public()
            ->args([
                service('security.untracked_token_storage'),
                service_locator([
                    'request_stack' => service('request_stack'),
                ]),
            ])
            ->tag('kernel.reset', ['method' => 'disableUsageTracking'])
            ->tag('kernel.reset', ['method' => 'setToken'])
            ->tag('container.private', ['package' => 'symfony/security-bundle', 'version' => '5.3'])
        ->alias(TokenStorageInterface::class, 'security.token_storage')

        ->set('security.untracked_token_storage', TokenStorage::class)

        ->set('security.helper', Security::class)
            ->args([service_locator([
                'security.token_storage' => service('security.token_storage'),
                'security.authorization_checker' => service('security.authorization_checker'),
            ])])
        ->alias(Security::class, 'security.helper')

        ->set('security.user_value_resolver', UserValueResolver::class)
            ->args([
                service('security.token_storage'),
            ])
            ->tag('controller.argument_value_resolver', ['priority' => 40])

        // Authentication related services
        ->set('security.authentication.trust_resolver', AuthenticationTrustResolver::class)

        ->set('security.authentication.session_strategy', SessionAuthenticationStrategy::class)
            ->args([param('security.authentication.session_strategy.strategy')])
        ->alias(SessionAuthenticationStrategyInterface::class, 'security.authentication.session_strategy')

        ->set('security.authentication.session_strategy_noop', SessionAuthenticationStrategy::class)
            ->args(['none'])

        ->set('security.encoder_factory.generic', EncoderFactory::class)
            ->args([
                [],
            ])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use "security.password_hasher_factory" instead.')
        ->alias('security.encoder_factory', 'security.encoder_factory.generic')
            ->deprecate('symfony/security-bundle', '5.3', 'The "%alias_id%" service is deprecated, use "security.password_hasher_factory" instead.')
        ->alias(EncoderFactoryInterface::class, 'security.encoder_factory')
            ->deprecate('symfony/security-bundle', '5.3', 'The "%alias_id%" service is deprecated, use "'.PasswordHasherFactoryInterface::class.'" instead.')

        ->set('security.user_password_encoder.generic', UserPasswordEncoder::class)
            ->args([service('security.encoder_factory')])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use "security.user_password_hasher" instead.')
        ->alias('security.password_encoder', 'security.user_password_encoder.generic')
            ->public()
            ->deprecate('symfony/security-bundle', '5.3', 'The "%alias_id%" service is deprecated, use "security.password_hasher"" instead.')
        ->alias(UserPasswordEncoderInterface::class, 'security.password_encoder')
            ->deprecate('symfony/security-bundle', '5.3', 'The "%alias_id%" service is deprecated, use "'.UserPasswordHasherInterface::class.'" instead.')

        ->set('security.user_checker', InMemoryUserChecker::class)

        ->set('security.expression_language', ExpressionLanguage::class)
            ->args([service('cache.security_expression_language')->nullOnInvalid()])

        ->set('security.authentication_utils', AuthenticationUtils::class)
            ->args([service('request_stack')])
        ->alias(AuthenticationUtils::class, 'security.authentication_utils')

        // Authorization related services
        ->set('security.access.decision_manager', AccessDecisionManager::class)
            ->args([[]])
        ->alias(AccessDecisionManagerInterface::class, 'security.access.decision_manager')

        ->set('security.role_hierarchy', RoleHierarchy::class)
            ->args([param('security.role_hierarchy.roles')])
        ->alias(RoleHierarchyInterface::class, 'security.role_hierarchy')

        // Security Voters
        ->set('security.access.simple_role_voter', RoleVoter::class)
            ->tag('security.voter', ['priority' => 245])

        ->set('security.access.authenticated_voter', AuthenticatedVoter::class)
            ->args([service('security.authentication.trust_resolver')])
            ->tag('security.voter', ['priority' => 250])

        ->set('security.access.role_hierarchy_voter', RoleHierarchyVoter::class)
            ->args([service('security.role_hierarchy')])
            ->tag('security.voter', ['priority' => 245])

        ->set('security.access.expression_voter', ExpressionVoter::class)
            ->args([
                service('security.expression_language'),
                service('security.authentication.trust_resolver'),
                service('security.authorization_checker'),
                service('security.role_hierarchy')->nullOnInvalid(),
            ])
            ->tag('security.voter', ['priority' => 245])

        ->set('security.impersonate_url_generator', ImpersonateUrlGenerator::class)
        ->args([
            service('request_stack'),
            service('security.firewall.map'),
            service('security.token_storage'),
        ])

        // Firewall related services
        ->set('security.firewall', FirewallListener::class)
            ->args([
                service('security.firewall.map'),
                service('event_dispatcher'),
                service('security.logout_url_generator'),
            ])
            ->tag('kernel.event_subscriber')
        ->alias(Firewall::class, 'security.firewall')

        ->set('security.firewall.map', FirewallMap::class)
            ->args([
                abstract_arg('Firewall context locator'),
                abstract_arg('Request matchers'),
            ])
        ->alias(FirewallMapInterface::class, 'security.firewall.map')

        ->set('security.firewall.context', FirewallContext::class)
            ->abstract()
            ->args([
                [],
                service('security.exception_listener'),
                abstract_arg('LogoutListener'),
                abstract_arg('FirewallConfig'),
            ])

        ->set('security.firewall.lazy_context', LazyFirewallContext::class)
            ->abstract()
            ->args([
                [],
                service('security.exception_listener'),
                abstract_arg('LogoutListener'),
                abstract_arg('FirewallConfig'),
                service('security.untracked_token_storage'),
            ])

        ->set('security.firewall.config', FirewallConfig::class)
            ->abstract()
            ->args([
                abstract_arg('name'),
                abstract_arg('user_checker'),
                abstract_arg('request_matcher'),
                false, // security enabled
                false, // stateless
                null,
                null,
                null,
                null,
                null,
                [], // listeners
                null, // switch_user
            ])

        ->set('security.logout_url_generator', LogoutUrlGenerator::class)
            ->args([
                service('request_stack')->nullOnInvalid(),
                service('router')->nullOnInvalid(),
                service('security.token_storage')->nullOnInvalid(),
            ])

        // Provisioning
        ->set('security.user.provider.missing', MissingUserProvider::class)
            ->abstract()
            ->args([
                abstract_arg('firewall'),
            ])

        ->set('security.user.provider.in_memory', InMemoryUserProvider::class)
            ->abstract()

        ->set('security.user.provider.ldap', LdapUserProvider::class)
            ->abstract()
            ->args([
                abstract_arg('security.ldap.ldap'),
                abstract_arg('base dn'),
                abstract_arg('search dn'),
                abstract_arg('search password'),
                abstract_arg('default_roles'),
                abstract_arg('uid key'),
                abstract_arg('filter'),
                abstract_arg('password_attribute'),
                abstract_arg('extra_fields (email etc)'),
            ])

        ->set('security.user.provider.chain', ChainUserProvider::class)
            ->abstract()

        ->set('security.http_utils', HttpUtils::class)
            ->args([
                service('router')->nullOnInvalid(),
                service('router')->nullOnInvalid(),
            ])
        ->alias(HttpUtils::class, 'security.http_utils')

        // Validator
        ->set('security.validator.user_password', UserPasswordValidator::class)
            ->args([
                service('security.token_storage'),
                service('security.password_hasher_factory'),
            ])
            ->tag('validator.constraint_validator', ['alias' => 'security.validator.user_password'])

        // Cache
        ->set('cache.security_expression_language')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')

        // Cache Warmers
        ->set('security.cache_warmer.expression', ExpressionCacheWarmer::class)
            ->args([
                [],
                service('security.expression_language'),
            ])
            ->tag('kernel.cache_warmer')
    ;
};
