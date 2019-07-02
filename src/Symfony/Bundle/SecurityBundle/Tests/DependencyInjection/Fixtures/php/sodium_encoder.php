<?php

$this->load('container1.php');

$container->loadFromExtension('security', [
    'encoders' => [
        'JMS\FooBundle\Entity\User7' => [
            'algorithm' => 'sodium',
            'time_cost' => 8,
            'memory_cost' => 128 * 1024,
        ],
    ],
]);
