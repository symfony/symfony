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

use Symfony\Component\FeatureFlags\FeatureChecker;
use Symfony\Component\FeatureFlags\FeatureCheckerInterface;
use Symfony\Component\FeatureFlags\FeatureCollection;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('feature_flags.feature_collection', FeatureCollection::class)
        ->args([
            '$providers' => tagged_iterator('feature_flags.feature_provider'),
        ])
    ;

    $services->set('feature_flags.feature_checker', FeatureChecker::class)
        ->args([
            '$features' => service('feature_flags.feature_collection'),
            '$whenNotFound' => false,
        ])
    ;
    $services->alias(FeatureCheckerInterface::class, service('feature_flags.feature_checker'));
};
