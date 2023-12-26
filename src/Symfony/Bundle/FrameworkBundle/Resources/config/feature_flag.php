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

use Symfony\Component\FeatureFlag\FeatureChecker;
use Symfony\Component\FeatureFlag\FeatureCheckerInterface;
use Symfony\Component\FeatureFlag\FeatureRegistry;
use Symfony\Component\FeatureFlag\FeatureRegistryInterface;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('feature_flag.feature_registry', FeatureRegistry::class)
            ->args([
                '$features' => abstract_arg('Defined in FeatureFlagPass.'),
            ])
            ->alias(FeatureRegistryInterface::class, 'feature_flag.feature_registry')

        ->set('feature_flag.feature_checker', FeatureChecker::class)
            ->args([
                '$featureRegistry' => service('feature_flag.feature_registry'),
                '$default' => false,
            ])
            ->alias(FeatureCheckerInterface::class, 'feature_flag.feature_checker')
    ;
};
