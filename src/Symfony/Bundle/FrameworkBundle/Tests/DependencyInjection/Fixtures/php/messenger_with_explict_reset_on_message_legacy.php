<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'messenger' => [
        'reset_on_message' =>  true,
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
