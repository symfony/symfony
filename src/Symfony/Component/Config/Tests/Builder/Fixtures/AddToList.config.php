<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Config\AddToListConfig;

return static function (AddToListConfig $config) {
    $config->translator()->fallbacks(['sv', 'fr', 'es']);
    $config->translator()->source('\\Acme\\Foo', 'yellow');
    $config->translator()->source('\\Acme\\Bar', 'green');

    $config->messenger([
        'routing' => [
            'Foo\\MyArrayMessage' => [
                'senders' => ['workqueue'],
            ],
        ],
    ]);
    $config->messenger()
        ->routing('Foo\\Message')->senders(['workqueue']);
    $config->messenger()
        ->routing('Foo\\DoubleMessage')->senders(['sync', 'workqueue']);

    $config->messenger()->receiving()
        ->color('blue')
        ->priority(10);
    $config->messenger()->receiving()
        ->color('red')
        ->priority(5);
};
