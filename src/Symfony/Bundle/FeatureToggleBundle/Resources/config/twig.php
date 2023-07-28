<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bundle\FeatureToggleBundle\Twig\FeatureEnabledExtension;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('toggle_feature.twig_extension', FeatureEnabledExtension::class)
        ->args([
            service('toggle_feature.feature_checker'),
        ])
        ->tag('twig.extension')
    ;
};
