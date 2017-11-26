<?php

$container->loadFromExtension('security', array(
    'encoders' => array(
        'JMS\FooBundle\Entity\User1' => 'plaintext',
        'JMS\FooBundle\Entity\User2' => array(
            'algorithm' => 'sha1',
            'encode_as_base64' => false,
            'iterations' => 5,
        ),
        'JMS\FooBundle\Entity\User3' => array(
            'algorithm' => 'md5',
        ),
        'JMS\FooBundle\Entity\User4' => array(
            'id' => 'security.encoder.foo',
        ),
        'JMS\FooBundle\Entity\User5' => array(
            'algorithm' => 'pbkdf2',
            'hash_algorithm' => 'sha1',
            'encode_as_base64' => false,
            'iterations' => 5,
            'key_length' => 30,
        ),
        'JMS\FooBundle\Entity\User6' => array(
            'algorithm' => 'bcrypt',
            'cost' => 15,
        ),
    ),
    'providers' => array(
        'default' => array(
            'memory' => array(
                'users' => array(
                    'foo' => array('password' => 'foo', 'roles' => 'ROLE_USER'),
                ),
            ),
        ),
        'digest' => array(
            'memory' => array(
                'users' => array(
                    'foo' => array('password' => 'foo', 'roles' => 'ROLE_USER, ROLE_ADMIN'),
                ),
            ),
        ),
        'basic' => array(
            'memory' => array(
                'users' => array(
                    'foo' => array('password' => '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33', 'roles' => 'ROLE_SUPER_ADMIN'),
                    'bar' => array('password' => '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33', 'roles' => array('ROLE_USER', 'ROLE_ADMIN')),
                ),
            ),
        ),
        'service' => array(
            'id' => 'user.manager',
        ),
        'chain' => array(
            'chain' => array(
                'providers' => array('service', 'basic'),
            ),
        ),
    ),

    'firewalls' => array(
        'simple' => array('provider' => 'default', 'pattern' => '/login', 'security' => false),
        'secure' => array('stateless' => true,
            'provider' => 'default',
            'http_basic' => true,
            'form_login' => true,
            'anonymous' => true,
            'switch_user' => array('stateless' => true),
            'x509' => true,
            'remote_user' => true,
            'logout' => true,
            'remember_me' => array('secret' => 'TheSecret'),
            'user_checker' => null,
        ),
        'host' => array(
            'provider' => 'default',
            'pattern' => '/test',
            'host' => 'foo\\.example\\.org',
            'methods' => array('GET', 'POST'),
            'anonymous' => true,
            'http_basic' => true,
        ),
        'with_user_checker' => array(
            'provider' => 'default',
            'user_checker' => 'app.user_checker',
            'anonymous' => true,
            'http_basic' => true,
        ),
    ),

    'access_control' => array(
        array('path' => '/blog/524', 'role' => 'ROLE_USER', 'requires_channel' => 'https', 'methods' => array('get', 'POST')),
        array('path' => '/blog/.*', 'role' => 'IS_AUTHENTICATED_ANONYMOUSLY'),
        array('path' => '/blog/524', 'role' => 'IS_AUTHENTICATED_ANONYMOUSLY', 'allow_if' => "token.getUsername() matches '/^admin/'"),
    ),

    'role_hierarchy' => array(
        'ROLE_ADMIN' => 'ROLE_USER',
        'ROLE_SUPER_ADMIN' => array('ROLE_USER', 'ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'),
        'ROLE_REMOTE' => 'ROLE_USER,ROLE_ADMIN',
    ),
));
