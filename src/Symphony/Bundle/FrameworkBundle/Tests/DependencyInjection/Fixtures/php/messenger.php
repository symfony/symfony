<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'routing' => array(
            'App\Bar' => array('sender.bar', 'sender.biz'),
            'App\Foo' => 'sender.foo',
        ),
    ),
));
