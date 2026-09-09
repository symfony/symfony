<?php

use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;

$container->loadFromExtension('messenger', [
    'routing' => [
        DummyMessage::class => ['sender.bar', 'sender.biz'],
        SecondMessage::class => 'sender.foo',
    ],
    'transports' => [
        'sender.biz' => 'null://',
        'sender.bar' => 'null://',
        'sender.foo' => 'null://',
    ],
]);
