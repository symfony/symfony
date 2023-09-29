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

use Symfony\Bundle\FeatureFlagsBundle\Strategy\RequestStackAttributeStrategy;
use Symfony\Bundle\FeatureFlagsBundle\Strategy\RequestStackHeaderStrategy;
use Symfony\Bundle\FeatureFlagsBundle\Strategy\RequestStackQueryStrategy;
use Symfony\Component\FeatureFlags\Strategy\AffirmativeStrategy;
use Symfony\Component\FeatureFlags\Strategy\DateStrategy;
use Symfony\Component\FeatureFlags\Strategy\EnvStrategy;
use Symfony\Component\FeatureFlags\Strategy\GrantStrategy;
use Symfony\Component\FeatureFlags\Strategy\NotStrategy;
use Symfony\Component\FeatureFlags\Strategy\PriorityStrategy;
use Symfony\Component\FeatureFlags\Strategy\UnanimousStrategy;

return static function (ContainerConfigurator $container) {
    $prefix = 'feature_flags.abstract_strategy.';

    $services = $container->services();
    $services->set($prefix.'grant', GrantStrategy::class)->abstract();
    $services->set($prefix.'not', NotStrategy::class)->abstract()->args([
        '$inner' => abstract_arg('Defined in FeatureFlagsExtension'),
    ]);
    $services->set($prefix.'env', EnvStrategy::class)->abstract()->args([
        '$envName' => abstract_arg('Defined in FeatureFlagsExtension'),
    ]);
    $services->set($prefix.'date', DateStrategy::class)->abstract()->args([
        '$since' => abstract_arg('Defined in FeatureFlagsExtension'),
        '$until' => abstract_arg('Defined in FeatureFlagsExtension'),
        '$includeSince' => abstract_arg('Defined in FeatureFlagsExtension'),
        '$includeUntil' => abstract_arg('Defined in FeatureFlagsExtension'),
        '$clock' => service('clock')->nullOnInvalid(),
    ]);
    $services->set($prefix.'request_attribute', RequestStackAttributeStrategy::class)->abstract()->args([
        '$attributeName' => abstract_arg('Defined in FeatureFlagsExtension'),
    ])->call('setRequestStack', [service('request_stack')->nullOnInvalid()]);
    $services->set($prefix.'request_header', RequestStackHeaderStrategy::class)->abstract()->args([
        '$headerName' => abstract_arg('Defined in FeatureFlagsExtension'),
    ])->call('setRequestStack', [service('request_stack')->nullOnInvalid()]);
    $services->set($prefix.'request_query', RequestStackQueryStrategy::class)->abstract()->args([
        '$queryParameterName' => abstract_arg('Defined in FeatureFlagsExtension'),
    ])->call('setRequestStack', [service('request_stack')->nullOnInvalid()]);
    $services->set($prefix.'priority', PriorityStrategy::class)->abstract()->args([
        '$strategies' => abstract_arg('Defined in FeatureFlagsExtension'),
    ]);
    $services->set($prefix.'affirmative', AffirmativeStrategy::class)->abstract()->args([
        '$strategies' => abstract_arg('Defined in FeatureFlagsExtension'),
    ]);
    $services->set($prefix.'unanimous', UnanimousStrategy::class)->abstract()->args([
        '$strategies' => abstract_arg('Defined in FeatureFlagsExtension'),
    ]);
};
