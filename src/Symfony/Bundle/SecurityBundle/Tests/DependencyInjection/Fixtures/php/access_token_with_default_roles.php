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

use Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\AccessToken\AccessTokenHandler;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(AccessTokenHandler::class)
        ->public();

    $container->extension('security', [
        'firewalls' => [
            'with_string' => [
                'access_token' => [
                    'token_handler' => AccessTokenHandler::class,
                    'default_roles' => 'ROLE_FOO',
                ],
            ],
            'with_array' => [
                'access_token' => [
                    'token_handler' => AccessTokenHandler::class,
                    'default_roles' => ['ROLE_FOO', 'ROLE_BAR'],
                ],
            ],
        ],
    ]);
};
