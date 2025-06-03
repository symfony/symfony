<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    '@hotwired/stimulus' => [
        'version' => '3.2.1',
    ],
    'lodash' => [
        'version' => '4.17.21',
    ],
    'app' => [
        'path' => 'app.js',
    ],
    'other_app' => [
        // "namespaced_assets2" is defined as a namespaced path in the test
        'path' => 'namespaced_assets2/app2.js',
    ],
    'app.css' => [
        'path' => 'namespaced_assets2/styles/app.css',
        'type' => 'css',
    ],
    'app2.css' => [
        'path' => 'namespaced_assets2/styles/app2.css',
        'type' => 'css',
    ],
];
