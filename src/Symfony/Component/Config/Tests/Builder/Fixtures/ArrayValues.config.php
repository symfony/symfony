<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Config\ArrayValuesConfig;

return static function (ArrayValuesConfig $config) {
    $config->transports('foo')->dsn('bar');
    $config->transports('bar', ['dsn' => 'foobar']);

    $placeConfig = $config->places()->name('foo');
    $placeConfig->metadata('key', 'value');
    $placeConfig->metadata('key2', ['some_other_value', true]);

    $config->places(['name' => 'bar', 'metadata' => ['some' => 'metadata']]);

    $config->errorPages()->withTrace(false);
};
