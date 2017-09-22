<?php

$container->loadFromExtension('security', array(
    'firewalls' => array(
        'main' => array(
            'form_login' => array(
                'login_path' => '/login',
            ),
        'logout_on_user_change' => true,
        ),
    ),
    'role_hierarchy' => array(
        'FOO' => 'BAR',
        'ADMIN' => 'USER',
    ),
));
