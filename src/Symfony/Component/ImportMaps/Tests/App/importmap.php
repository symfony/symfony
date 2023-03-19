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
        'url' => 'https://ga.jspm.io/npm:@hotwired/stimulus@3.2.1/dist/stimulus.js',
    ],
];
