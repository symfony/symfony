<?php

$this->load('container1.php');

$container->loadFromExtension('security', [
    'encoders' => [
        'JMS\FooBundle\Entity\User7' => [
            'algorithm' => 'bcrypt',
            'cost' => 15,
        ],
    ],
]);
