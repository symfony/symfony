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

use Symfony\Bundle\SecurityBundle\RememberMe\FirewallAwareRememberMeHandler;
use Symfony\Component\Security\Core\Signature\SignatureHasher;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticator;
use Symfony\Component\Security\Http\EventListener\CheckRememberMeConditionsListener;
use Symfony\Component\Security\Http\EventListener\RememberMeListener;
use Symfony\Component\Security\Http\RememberMe\PersistentRememberMeHandler;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use Symfony\Component\Security\Http\RememberMe\SignatureRememberMeHandler;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.authenticator.remember_me_signature_hasher', SignatureHasher::class)
            ->args([
                service('property_accessor'),
                abstract_arg('signature properties'),
                '%kernel.secret%',
                null,
                null,
            ])

        ->set('security.authenticator.signature_remember_me_handler', SignatureRememberMeHandler::class)
            ->abstract()
            ->args([
                abstract_arg('signature hasher'),
                abstract_arg('user provider'),
                service('request_stack'),
                abstract_arg('options'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.authenticator.persistent_remember_me_handler', PersistentRememberMeHandler::class)
            ->abstract()
            ->args([
                abstract_arg('token provider'),
                param('kernel.secret'),
                abstract_arg('user provider'),
                service('request_stack'),
                abstract_arg('options'),
                service('logger')->nullOnInvalid(),
                abstract_arg('token verifier'),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.authenticator.firewall_aware_remember_me_handler', FirewallAwareRememberMeHandler::class)
            ->args([
                service('security.firewall.map'),
                tagged_locator('security.remember_me_handler', 'firewall'),
                service('request_stack'),
            ])
        ->alias(RememberMeHandlerInterface::class, 'security.authenticator.firewall_aware_remember_me_handler')

        ->set('security.listener.check_remember_me_conditions', CheckRememberMeConditionsListener::class)
            ->abstract()
            ->args([
                abstract_arg('options'),
                service('logger')->nullOnInvalid(),
            ])

        ->set('security.listener.remember_me', RememberMeListener::class)
            ->abstract()
            ->args([
                abstract_arg('remember me handler'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        ->set('security.authenticator.remember_me', RememberMeAuthenticator::class)
            ->abstract()
            ->args([
                abstract_arg('remember me handler'),
                param('kernel.secret'),
                service('security.token_storage'),
                abstract_arg('options'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'security'])

        // Cache
        ->set('cache.security_token_verifier')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')
    ;
};
