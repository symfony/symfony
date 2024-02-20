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

use Symfony\Component\AccessToken\AccessTokenManager;
use Symfony\Component\AccessToken\AccessTokenManagerInterface;
use Symfony\Component\AccessToken\Manager\CacheAccessTokenManagerDecorator;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('asset.request_context.base_path', null)
        ->set('asset.request_context.secure', null)
    ;

    $container->services()
        ->set('access_token.manager')
            ->class(AccessTokenManager::class)
            ->args([
                tagged_iterator('access_token.provider'),
                tagged_iterator('access_token.factory', 'scheme'),
                service('http_client'),
            ])

        ->alias(AccessTokenManagerInterface::class, 'access_token.manager')

        ->set('cache.access_token')
            ->parent('cache.app')
            ->private()
            ->tag('cache.pool')

        ->set('access_token.manager.cache')
            ->class(CacheAccessTokenManagerDecorator::class)
            ->decorate('access_token.manager', null, 100)
            ->args([
                service('.inner'),
                service('cache.access_token'),
            ])
    ;
};
