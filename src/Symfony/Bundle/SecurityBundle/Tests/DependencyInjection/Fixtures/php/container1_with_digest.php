<?php

$container->loadFromExtension('security', [
    'acl' => [],
    'encoders' => [
        'JMS\FooBundle\Entity\User1' => 'plaintext',
        'JMS\FooBundle\Entity\User2' => [
            'algorithm' => 'sha1',
            'encode_as_base64' => false,
            'iterations' => 5,
        ],
        'JMS\FooBundle\Entity\User3' => [
            'algorithm' => 'md5',
        ],
        'JMS\FooBundle\Entity\User4' => [
            'id' => 'security.encoder.foo',
        ],
        'JMS\FooBundle\Entity\User5' => [
            'algorithm' => 'pbkdf2',
            'hash_algorithm' => 'sha1',
            'encode_as_base64' => false,
            'iterations' => 5,
            'key_length' => 30,
        ],
        'JMS\FooBundle\Entity\User6' => [
            'algorithm' => 'bcrypt',
            'cost' => 15,
        ],
    ],
    'providers' => [
        'default' => [
            'memory' => [
                'users' => [
                    'foo' => ['password' => 'foo', 'roles' => 'ROLE_USER'],
                ],
            ],
        ],
        'digest' => [
            'memory' => [
                'users' => [
                    'foo' => ['password' => 'foo', 'roles' => 'ROLE_USER, ROLE_ADMIN'],
                ],
            ],
        ],
        'basic' => [
            'memory' => [
                'users' => [
                    'foo' => ['password' => '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33', 'roles' => 'ROLE_SUPER_ADMIN'],
                    'bar' => ['password' => '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33', 'roles' => ['ROLE_USER', 'ROLE_ADMIN']],
                ],
            ],
        ],
        'service' => [
            'id' => 'user.manager',
        ],
        'chain' => [
            'chain' => [
                'providers' => ['service', 'basic'],
            ],
        ],
    ],

    'firewalls' => [
        'simple' => ['provider' => 'default', 'pattern' => '/login', 'security' => false],
        'secure' => ['stateless' => true,
            'provider' => 'default',
            'http_basic' => true,
            'http_digest' => ['secret' => 'TheSecret'],
            'form_login' => true,
            'anonymous' => true,
            'switch_user' => ['stateless' => true],
            'x509' => true,
            'remote_user' => true,
            'logout' => true,
            'remember_me' => ['secret' => 'TheSecret'],
            'user_checker' => null,
            'logout_on_user_change' => true,
        ],
        'host' => [
            'provider' => 'default',
            'pattern' => '/test',
            'host' => 'foo\\.example\\.org',
            'methods' => ['GET', 'POST'],
            'anonymous' => true,
            'http_basic' => true,
            'logout_on_user_change' => true,
        ],
        'with_user_checker' => [
            'provider' => 'default',
            'user_checker' => 'app.user_checker',
            'anonymous' => true,
            'http_basic' => true,
            'logout_on_user_change' => true,
        ],
    ],

    'access_control' => [
        ['path' => '/blog/524', 'role' => 'ROLE_USER', 'requires_channel' => 'https', 'methods' => ['get', 'POST']],
        ['path' => '/blog/.*', 'role' => 'IS_AUTHENTICATED_ANONYMOUSLY'],
        ['path' => '/blog/524', 'role' => 'IS_AUTHENTICATED_ANONYMOUSLY', 'allow_if' => "token.getUsername() matches '/^admin/'"],
    ],

    'role_hierarchy' => [
        'ROLE_ADMIN' => 'ROLE_USER',
        'ROLE_SUPER_ADMIN' => ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
        'ROLE_REMOTE' => 'ROLE_USER,ROLE_ADMIN',
    ],
]);
