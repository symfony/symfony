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

return static function (PlaceholdersConfig $config) {
    $config->enabled(env('FOO_ENABLED')->bool());
    $config->favoriteFloat(param('eulers_number'));
    $config->goodIntegers(env('MY_INTEGERS')->json());
};
