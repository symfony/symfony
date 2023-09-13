<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\FeatureToggle\Provider\InMemoryProvider;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('feature_toggle.provider.in_memory', InMemoryProvider::class)
        ->tag('feature_toggle.feature_provider', ['priority' => 16])
    ;
};
