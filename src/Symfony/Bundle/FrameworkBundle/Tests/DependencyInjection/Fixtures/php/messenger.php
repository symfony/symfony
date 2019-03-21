<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;

$container->loadFromExtension('framework', [
    'messenger' => [
        'default_serializer' => false,
        'routing' => [
            FooMessage::class => ['sender.bar', 'sender.biz'],
            BarMessage::class => 'sender.foo',
        ],
    ],
]);
