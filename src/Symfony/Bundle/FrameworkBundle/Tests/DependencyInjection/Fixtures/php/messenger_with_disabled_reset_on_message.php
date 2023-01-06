<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'messenger' => [
        'reset_on_message' =>  false,
        'routing' => [
            FooMessage::class => ['sender.bar', 'sender.biz'],
            BarMessage::class => 'sender.foo',
        ],
        'transports' => [
            'sender.biz' => 'null://',
            'sender.bar' => 'null://',
            'sender.foo' => 'null://',
        ],
    ],
]);
