<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Config\PlaceholdersConfig;

return static function (PlaceholdersConfig $config) {
    $config->enabled(env('FOO_ENABLED')->bool());
    $config->favoriteFloat(param('eulers_number'));
    $config->goodIntegers(env('MY_INTEGERS')->json());
};
