<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'routing' => [
            'App\Foo' => 'sender.foo',
            'App\Bar' => 'sender.bar',
        ],
    ),
));
