<?php

$this->load('legacy_encoders.php');

$container->loadFromExtension('security', [
    'encoders' => [
        'JMS\FooBundle\Entity\User7' => [
            'algorithm' => 'argon2i',
            'memory_cost' => 256,
            'time_cost' => 1,
        ],
    ],
]);
