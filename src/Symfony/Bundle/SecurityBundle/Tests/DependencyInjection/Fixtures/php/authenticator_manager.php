<?php

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;

$container->loadFromExtension('security', [
    'firewalls' => [
        'main' => [
            'required_badges' => [CsrfTokenBadge::class, 'RememberMeBadge'],
            'login_link' => [
                'check_route' => 'login_check',
                'check_post_only' => true,
                'signature_properties' => ['id', 'email'],
                'max_uses' => 1,
                'lifetime' => 3600,
                'used_link_cache' => 'cache.redis',
            ],
            'login_throttling' => [
                'limiter' => 'app.rate_limiter',
            ],
        ],
    ],
]);
