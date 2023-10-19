<?php

$this->load('container1.php');

$container->loadFromExtension('security', [
    'password_hashers' => [
        'JMS\FooBundle\Entity\User7' => [
            'algorithm' => 'argon2i',
            'memory_cost' => 256,
            'time_cost' => 1,
            'migrate_from' => 'bcrypt',
        ],
    ],
]);
