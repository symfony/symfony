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

use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bridge\Twig\Extension\CsrfRuntime;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.csrf.token_generator', UriSafeTokenGenerator::class)

        ->alias(TokenGeneratorInterface::class, 'security.csrf.token_generator')

        ->set('security.csrf.token_storage', SessionTokenStorage::class)
            ->args([service('request_stack')])

        ->alias(TokenStorageInterface::class, 'security.csrf.token_storage')

        ->set('security.csrf.token_manager', CsrfTokenManager::class)
            ->args([
                service('security.csrf.token_generator'),
                service('security.csrf.token_storage'),
                service('request_stack')->ignoreOnInvalid(),
            ])

        ->alias(CsrfTokenManagerInterface::class, 'security.csrf.token_manager')

        ->set('twig.runtime.security_csrf', CsrfRuntime::class)
            ->args([service('security.csrf.token_manager')])
            ->tag('twig.runtime')

        ->set('twig.extension.security_csrf', CsrfExtension::class)
            ->tag('twig.extension')

        ->set('security.csrf.same_origin_token_manager', SameOriginCsrfTokenManager::class)
            ->decorate('security.csrf.token_manager')
            ->args([
                service('request_stack'),
                service('logger')->nullOnInvalid(),
                service('.inner'),
                abstract_arg('framework.csrf_protection.stateless_token_ids'),
                param('framework.csrf_protection.check_header'),
                param('framework.csrf_protection.cookie_name'),
            ])
            ->tag('monolog.logger', ['channel' => 'request'])
            ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse'])
    ;
};
