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

return static function (ContainerConfigurator $container) {
    $container->extension('security', [
        'firewalls' => [
            'with_string' => [
                'access_token' => [
                    'token_handler' => [
                        'oidc' => [
                            'algorithms' => ['RS256'],
                            'issuers' => ['https://www.example.com'],
                            'audience' => 'audience',
                            'keyset' => '{"keys":[{"kty":"RSA","n":"abc","e":"AQAB"}]}',
                        ],
                    ],
                    'default_roles' => 'ROLE_FOO',
                ],
            ],
            'with_array' => [
                'access_token' => [
                    'token_handler' => [
                        'oidc' => [
                            'algorithms' => ['RS256'],
                            'issuers' => ['https://www.example.com'],
                            'audience' => 'audience',
                            'keyset' => '{"keys":[{"kty":"RSA","n":"abc","e":"AQAB"}]}',
                        ],
                    ],
                    'default_roles' => ['ROLE_FOO', 'ROLE_BAR'],
                ],
            ],
        ],
    ]);
};
