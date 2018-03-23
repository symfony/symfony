<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'routing' => array(
            'App\Foo' => 'sender.foo',
            'App\Bar' => 'sender.bar',
        ),
    ),
));
