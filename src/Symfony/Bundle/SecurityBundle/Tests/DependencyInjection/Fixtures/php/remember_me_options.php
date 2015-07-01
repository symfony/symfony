<?php

$container->loadFromExtension('security', array(
    'providers' => array(
        'default' => array('id' => 'foo'),
    ),

    'firewalls' => array(
        'main' => array(
            'form_login' => true,
            'remember_me' => array(
                'secret' => 'TheSecret',
                'catch_exceptions' => false,
                'token_provider' => 'token_provider_id',
            ),
        ),
    ),
));
