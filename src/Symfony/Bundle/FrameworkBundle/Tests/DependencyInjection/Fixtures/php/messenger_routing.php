<?php

$container->setParameter('env(FOO_MESSAGE_SENDER)', 'amqp');

$container->loadFromExtension('framework', [
    'serializer' => true,
    'messenger' => [
        'serializer' => [
            'default_serializer' => 'messenger.transport.symfony_serializer',
        ],
        'routing' => [
            'Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\DummyMessage' => ['amqp', 'messenger.transport.audit'],
            'Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\SecondMessage' => [
                'senders' => ['amqp', 'audit'],
            ],
            'Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage' => ['%env(FOO_MESSAGE_SENDER)%'],
            '*' => 'amqp',
        ],
        'transports' => [
            'amqp' => 'amqp://localhost/%2f/messages',
            'audit' => 'null://',
        ],
    ],
]);
