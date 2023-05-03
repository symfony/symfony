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
        'url' => 'https://unpkg.com/@hotwired/stimulus@3.2.1/dist/stimulus.js',
        'preload' => true,
    ],
    'lodash' => [
        'url' => 'https://ga.jspm.io/npm:lodash@4.17.21/lodash.js',
        'preload' => false,
    ],
    'file6' => [
        'path' => 'subdir/file6.js',
        'preload' => true,
    ],
];
