<?php

if ('prod' !== $env) {
    return;
}

return [
    'acme' => [
        'color' => 'red',
    ],
];
