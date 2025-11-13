<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Config\PlaceholdersConfig;

return new PlaceholdersConfig([
    'enabled' => env('FOO_ENABLED')->bool(),
    'favorite_float' => param('eulers_number'),
    'good_integers' => env('MY_INTEGERS')->json(),
]);
