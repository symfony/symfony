<?php

return [
    'acme@optional' => [
        'color' => 'blue',
    ],
    'missing@optional' => [
        'color' => 'blue',
    ],
    'when@some-env' => [
        'acme@optional' => [
            'color' => 'red',
        ],
        'missing@optional' => [
            'color' => 'red',
        ],
    ],
];
