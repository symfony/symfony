<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bundle\FeatureToggleBundle\Strategy\RequestStackAttributeStrategy;
use Symfony\Bundle\FeatureToggleBundle\Strategy\RequestStackHeaderStrategy;
use Symfony\Bundle\FeatureToggleBundle\Strategy\RequestStackQueryStrategy;
use Symfony\Component\FeatureToggle\Strategy\AffirmativeStrategy;
use Symfony\Component\FeatureToggle\Strategy\DateStrategy;
use Symfony\Component\FeatureToggle\Strategy\EnvStrategy;
use Symfony\Component\FeatureToggle\Strategy\GrantStrategy;
use Symfony\Component\FeatureToggle\Strategy\NotStrategy;
use Symfony\Component\FeatureToggle\Strategy\PriorityStrategy;

return static function (ContainerConfigurator $container) {
    $prefix = 'feature_toggle.abstract_strategy.';

    $services = $container->services();
    $services->set($prefix.'grant', GrantStrategy::class)->abstract();
    $services->set($prefix.'not', NotStrategy::class)->abstract()->args([
        '$inner' => abstract_arg('Defined in FeatureToggleExtension'),
    ]);
    $services->set($prefix.'env', EnvStrategy::class)->abstract()->args([
        '$envName' => abstract_arg('Defined in FeatureToggleExtension'),
    ]);
    $services->set($prefix.'date', DateStrategy::class)->abstract()->args([
        '$from' => abstract_arg('Defined in FeatureToggleExtension'),
        '$until' => abstract_arg('Defined in FeatureToggleExtension'),
        '$includeFrom' => abstract_arg('Defined in FeatureToggleExtension'),
        '$includeUntil' => abstract_arg('Defined in FeatureToggleExtension'),
        '$clock' => service('clock')->nullOnInvalid(),
    ]);
    $services->set($prefix.'request_attribute', RequestStackAttributeStrategy::class)->abstract()->args([
        '$attributeName' => abstract_arg('Defined in FeatureToggleExtension'),
    ])->call('setRequestStack', [service('request_stack')->nullOnInvalid()]);
    $services->set($prefix.'request_header', RequestStackHeaderStrategy::class)->abstract()->args([
        '$headerName' => abstract_arg('Defined in FeatureToggleExtension'),
    ])->call('setRequestStack', [service('request_stack')->nullOnInvalid()]);
    $services->set($prefix.'request_query', RequestStackQueryStrategy::class)->abstract()->args([
        '$queryParameterName' => abstract_arg('Defined in FeatureToggleExtension'),
    ])->call('setRequestStack', [service('request_stack')->nullOnInvalid()]);
    $services->set($prefix.'priority', PriorityStrategy::class)->abstract()->args([
        '$strategies' => abstract_arg('Defined in FeatureToggleExtension'),
    ]);
    $services->set($prefix.'affirmative', AffirmativeStrategy::class)->abstract()->args([
        '$strategies' => abstract_arg('Defined in FeatureToggleExtension'),
    ]);
};
