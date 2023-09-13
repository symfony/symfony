<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bundle\FeatureToggleBundle\DataCollector\FeatureCheckerDataCollector;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('feature_toggle.data_collector', FeatureCheckerDataCollector::class)
        ->args([
            '$featureCollection' => service('feature_toggle.feature_collection')
        ])
        ->tag('data_collector', ['template' => '@FeatureToggle/Collector/profiler.html.twig', 'id' => 'feature_toggle'])
    ;
};
