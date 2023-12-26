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

use Symfony\Component\FeatureFlag\DataCollector\FeatureFlagDataCollector;
use Symfony\Component\FeatureFlag\Debug\TraceableFeatureChecker;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('debug.feature_flag.feature_checker', TraceableFeatureChecker::class)
            ->decorate('feature_flag.feature_checker')
            ->args([
                '$decorated' => service('debug.feature_flag.feature_checker.inner'),
            ])

        ->set('feature_flag.data_collector', FeatureFlagDataCollector::class)
            ->args([
                '$featureRegistry' => service('feature_flag.feature_registry'),
                '$featureChecker' => service('debug.feature_flag.feature_checker'),
            ])
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/feature_flag.html.twig', 'id' => 'feature_flag'])

    ;
};
