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

use Symfony\Component\FeatureToggle\Provider\InMemoryProvider;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('feature_toggle.provider.in_memory', InMemoryProvider::class)
        ->tag('feature_toggle.feature_provider', ['priority' => 16])
    ;
};
