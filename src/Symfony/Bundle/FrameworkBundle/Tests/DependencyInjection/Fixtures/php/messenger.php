<?php

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'serializer' => false,
        'routing' => array(
            FooMessage::class => array('sender.bar', 'sender.biz'),
            BarMessage::class => 'sender.foo',
        ),
    ),
));
