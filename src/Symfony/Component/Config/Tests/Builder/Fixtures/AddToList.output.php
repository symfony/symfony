<?php

return [
    'translator' => [
        'fallbacks' => ['sv', 'fr', 'es'],
        'sources' => [
            '\\Acme\\Foo' => 'yellow',
            '\\Acme\\Bar' => 'green',
        ]
    ],
    'messenger' => [
        'routing' => [
            'Foo\\Message'=> ['senders'=>['workqueue']],
            'Foo\\DoubleMessage' => ['senders'=>['sync', 'workqueue']],
        ],
        'receiving' => [
            ['priority'=>10, 'color'=>'blue'],
            ['priority'=>5, 'color'=>'red'],
        ]
    ],
];
