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

use Symfony\Component\FeatureFlags\DataCollector\FeatureCheckerDataCollector;
use Symfony\Component\FeatureFlags\Debug\TraceableFeatureChecker;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('feature_flags.data_collector', FeatureCheckerDataCollector::class)
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/feature_flags.html.twig', 'id' => 'feature_flags'])


        ->set('debug.feature_flags.feature_checker', TraceableFeatureChecker::class)
            ->decorate('feature_flags.feature_checker')
            ->args([
                '$featureChecker' => service('debug.feature_flags.feature_checker.inner'),
                '$dataCollector' => service('feature_flags.data_collector'),
            ])
    ;
};
