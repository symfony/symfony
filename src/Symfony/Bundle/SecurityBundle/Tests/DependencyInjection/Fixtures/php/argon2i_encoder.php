<?php

$container->loadFromExtension('security', array(
    'encoders' => array(
        'JMS\FooBundle\Entity\User7' => array(
            'algorithm' => 'argon2i',
        ),
    ),
    'providers' => array(
        'default' => array('id' => 'foo'),
    ),
    'firewalls' => array(
        'main' => array(
            'form_login' => false,
            'http_basic' => null,
        ),
    ),
));
