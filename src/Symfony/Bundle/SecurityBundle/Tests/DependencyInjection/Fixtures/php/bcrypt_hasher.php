<?php

$this->load('container1.php');

$container->loadFromExtension('security', [
    'enable_authenticator_manager' => true,
    'password_hashers' => [
        'JMS\FooBundle\Entity\User7' => [
            'algorithm' => 'bcrypt',
            'cost' => 15,
        ],
    ],
]);
