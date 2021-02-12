<?php

$this->load('legacy_encoders.php');

$container->loadFromExtension('security', [
    'encoders' => [
        'JMS\FooBundle\Entity\User7' => [
            'algorithm' => 'bcrypt',
            'cost' => 15,
        ],
    ],
]);
