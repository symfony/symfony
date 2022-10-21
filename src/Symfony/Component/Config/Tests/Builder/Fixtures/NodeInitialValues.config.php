<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Config\NodeInitialValuesConfig;

return static function (NodeInitialValuesConfig $config) {
    $config->someCleverName(['second' => 'foo', 'third' => null])->first('bar');
    $config->messenger()
        ->transports('fast_queue', ['dsn' => 'sync://'])
        ->serializer('acme');

    $config->messenger()
        ->transports('slow_queue')
        ->dsn('doctrine://')
        ->options(['table' => 'my_messages']);
};
