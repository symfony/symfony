<?php

$this->load('container1.php', $container);

$container->loadFromExtension('security', array(
    'encoders' => array(
        'JMS\FooBundle\Entity\User7' => array(
            'algorithm' => 'argon2i',
            'memory_cost' => 256,
            'time_cost' => 1,
            'threads' => 2,
        ),
    ),
));
