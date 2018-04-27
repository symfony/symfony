<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'routing' => array(
            'Symfony\Component\Messenger\Tests\Fixtures\DummyMessage' => array('amqp'),
            'Symfony\Component\Messenger\Tests\Fixtures\SecondMessage' => array('amqp', 'audit', null),
            '*' => 'amqp',
        ),
    ),
));
