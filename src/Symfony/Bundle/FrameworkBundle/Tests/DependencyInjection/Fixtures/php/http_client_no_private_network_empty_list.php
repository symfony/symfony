<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'http_client' => [
        'default_options' => [
            'no_private_network' => true,
        ],
    ],
]);
