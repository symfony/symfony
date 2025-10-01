<?php

$container->loadFromExtension('security', [
    'providers' => [
        'default' => [
            'memory' => null,
        ],
    ],
    'firewalls' => [
        'firewall1' => [
            'provider' => 'default',
            'access_token' => [
                'token_handler' => [
                    'oidc_user_info' => [
                        'base_uri' => 'https://www.example.com/realms/demo/protocol/openid-connect/userinfo',
                        'discovery' => [
                            'cache' => [
                                'id' => 'oidc_cache',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
]);

