<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'routing' => array(
            'Symfony\Component\Messenger\Tests\Fixtures\DummyMessage' => array('amqp', 'audit'),
            'Symfony\Component\Messenger\Tests\Fixtures\SecondMessage' => array(
                'senders' => array('amqp', 'audit'),
                'send_and_handle' => true,
            ),
            '*' => 'amqp',
        ),
    ),
));
