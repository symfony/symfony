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

use Symfony\Bundle\SecurityBundle\LoginLink\FirewallAwareLoginLinkHandler;
use Symfony\Component\Security\Http\Authenticator\LoginLinkAuthenticator;
use Symfony\Component\Security\Http\LoginLink\ExpiredLoginLinkStorage;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandler;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.authenticator.login_link', LoginLinkAuthenticator::class)
            ->abstract()
            ->args([
                abstract_arg('the login link handler instance'),
                service('security.http_utils'),
                abstract_arg('authentication success handler'),
                abstract_arg('authentication failure handler'),
                abstract_arg('options'),
            ])

        ->set('security.authenticator.abstract_login_link_handler', LoginLinkHandler::class)
            ->abstract()
            ->args([
                service('router'),
                abstract_arg('user provider'),
                service('property_accessor'),
                abstract_arg('signature properties'),
                '%kernel.secret%',
                abstract_arg('options'),
                abstract_arg('expired login link storage'),
            ])

        ->set('security.authenticator.expired_login_link_storage', ExpiredLoginLinkStorage::class)
            ->abstract()
            ->args([
                abstract_arg('cache pool service'),
                abstract_arg('expired login link storage'),
            ])

        ->set('security.authenticator.cache.expired_links')
            ->parent('cache.app')
            ->private()

        ->set('security.authenticator.firewall_aware_login_link_handler', FirewallAwareLoginLinkHandler::class)
            ->args([
                service('security.firewall.map'),
                tagged_locator('security.authenticator.login_linker', 'firewall'),
                service('request_stack'),
            ])
        ->alias(LoginLinkHandlerInterface::class, 'security.authenticator.firewall_aware_login_link_handler')

        ->set('security.authenticator.entity_login_link_user_handler', EntityLoginLinkUserHandler::class)
            ->abstract()
            ->args([
                service('doctrine'),
                abstract_arg('user entity class name'),
            ])

    ;
};
