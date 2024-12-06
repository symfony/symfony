<?php

$this->load('container1.php');

$container->loadFromExtension('security', [
    'erase_credentials' => false,
    'password_hashers' => [
        'JMS\FooBundle\Entity\User7' => [
            'algorithm' => 'bcrypt',
            'cost' => 15,
        ],
    ],
]);
