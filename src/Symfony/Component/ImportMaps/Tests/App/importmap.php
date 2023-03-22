<?php

return [
    'application' => [
        'path' => 'application.js',
    ],
    'controllers/hello_controller' => [
        'path' => 'controllers/hello_controller.js',
    ],
    '@hotwired/stimulus' => [
        'download' => true,
        'preload' => true,
        'url' => 'https://unpkg.com/@hotwired/stimulus@3.2.0/dist/stimulus.js',
    ],
];
