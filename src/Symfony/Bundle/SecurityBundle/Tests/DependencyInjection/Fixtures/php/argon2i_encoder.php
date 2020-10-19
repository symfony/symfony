<?php

$this->load('container1.php', $container);

$container->loadFromExtension('security', [
    'encoders' => [
        'JMS\FooBundle\Entity\User7' => [
            'algorithm' => 'argon2i',
            'memory_cost' => 256,
            'time_cost' => 1,
        ],
    ],
]);
