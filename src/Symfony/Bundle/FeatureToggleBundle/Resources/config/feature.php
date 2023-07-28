<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\FeatureToggle\FeatureChecker;
use Symfony\Component\FeatureToggle\FeatureCheckerInterface;
use Symfony\Component\FeatureToggle\FeatureCollection;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('toggle_feature.feature_collection', FeatureCollection::class)
        ->args([
            '$features' => [],
        ])
    ;

    $services->set('toggle_feature.feature_checker', FeatureChecker::class)
        ->args([
            '$features' => service('toggle_feature.feature_collection'),
            '$whenNotFound' => false,
        ])
    ;
    $services->alias(FeatureCheckerInterface::class, service('toggle_feature.feature_checker'));
};
