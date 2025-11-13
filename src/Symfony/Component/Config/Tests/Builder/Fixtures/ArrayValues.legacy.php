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

    $config->errorPages()->withTrace(false);
};
