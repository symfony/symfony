<?php

return [
    '@hotwired/stimulus' => [
        'url' => 'https://unpkg.com/@hotwired/stimulus@3.2.1/dist/stimulus.js',
        'preload' => true,
    ],
    'lodash' => [
        'url' => 'https://ga.jspm.io/npm:lodash@4.17.21/lodash.js',
        'downloaded_to' => 'vendor/lodash.js',
    ],
    'app' => [
        'path' => 'app.js',
        'preload' => true,
    ],
    'other_app' => [
        // "namespaced_assets2" is defined as a namespaced path in the test
        'path' => 'namespaced_assets2/app2.js',
    ]
];
