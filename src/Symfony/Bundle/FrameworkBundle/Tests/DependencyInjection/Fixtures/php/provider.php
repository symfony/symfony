<?php

$container->loadFromExtension('security', 'config', array(
    'providers' => array(
        'digest' => array(
            'users' => array(
                'foo' => array('password' => 'foo', 'roles' => 'ROLE_USER, ROLE_ADMIN'),
            ),
        ),
        'basic' => array(
            'password_encoder' => 'sha1',
            'users' => array(
                'foo' => array('password' => '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33', 'roles' => 'ROLE_SUPER_ADMIN'),
                'bar' => array('password' => '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33', 'roles' => array('ROLE_USER', 'ROLE_ADMIN')),
            ),
        ),
        'doctrine' => array(
            'entity' => array('class' => 'SecurityBundle:User', 'property' => 'username')
        ),
        'service' => array(
            'id' => 'user.manager',
        ),
    )
));
