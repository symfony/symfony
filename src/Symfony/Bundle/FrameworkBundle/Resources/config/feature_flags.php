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

use Symfony\Bundle\FrameworkBundle\Command\FeatureFlagsDebugCommand;
use Symfony\Component\FeatureFlags\FeatureChecker;
use Symfony\Component\FeatureFlags\FeatureCheckerInterface;
use Symfony\Component\FeatureFlags\FeatureCollection;
use Symfony\Component\FeatureFlags\Provider\LazyInMemoryProvider;
use Symfony\Component\FeatureFlags\Strategy\AffirmativeStrategy;
use Symfony\Component\FeatureFlags\Strategy\DateStrategy;
use Symfony\Component\FeatureFlags\Strategy\EnvStrategy;
use Symfony\Component\FeatureFlags\Strategy\GrantStrategy;
use Symfony\Component\FeatureFlags\Strategy\HttpFoundation\RequestStackAttributeStrategy;
use Symfony\Component\FeatureFlags\Strategy\HttpFoundation\RequestStackHeaderStrategy;
use Symfony\Component\FeatureFlags\Strategy\HttpFoundation\RequestStackQueryStrategy;
use Symfony\Component\FeatureFlags\Strategy\NotStrategy;
use Symfony\Component\FeatureFlags\Strategy\PriorityStrategy;
use Symfony\Component\FeatureFlags\Strategy\UnanimousStrategy;

return static function (ContainerConfigurator $container) {
    $strategyPrefix = 'feature_flags.abstract_strategy.';

    $container->services()

        ->set('feature_flags.feature_collection', FeatureCollection::class)
            ->args([
                '$providers' => tagged_iterator('feature_flags.feature_provider'),
            ])

        ->set('feature_flags.feature_checker', FeatureChecker::class)
            ->args([
                '$features' => service('feature_flags.feature_collection'),
                '$whenNotFound' => false,
            ])
        ->alias(FeatureCheckerInterface::class, 'feature_flags.feature_checker')

        ->set('feature_flags.provider.lazy_in_memory', LazyInMemoryProvider::class)
            ->args([
                '$features' => abstract_arg('Defined in FeatureFlagsExtension'),
            ])
            ->tag('feature_flags.feature_provider', ['priority' => 16])

        ->set($strategyPrefix.'grant', GrantStrategy::class)->abstract()
        ->set($strategyPrefix.'not', NotStrategy::class)->abstract()->args([
            '$inner' => abstract_arg('Defined in FeatureFlagsExtension'),
        ])
        ->set($strategyPrefix.'env', EnvStrategy::class)->abstract()->args([
            '$envName' => abstract_arg('Defined in FeatureFlagsExtension'),
        ])
        ->set($strategyPrefix.'date', DateStrategy::class)->abstract()->args([
            '$since' => abstract_arg('Defined in FeatureFlagsExtension'),
            '$until' => abstract_arg('Defined in FeatureFlagsExtension'),
            '$includeSince' => abstract_arg('Defined in FeatureFlagsExtension'),
            '$includeUntil' => abstract_arg('Defined in FeatureFlagsExtension'),
            '$clock' => service('clock')->nullOnInvalid(),
        ])
        ->set($strategyPrefix.'request_attribute', RequestStackAttributeStrategy::class)->abstract()->args([
            '$attributeName' => abstract_arg('Defined in FeatureFlagsExtension'),
        ])->call('setRequestStack', [service('request_stack')->nullOnInvalid()])
        ->set($strategyPrefix.'request_header', RequestStackHeaderStrategy::class)->abstract()->args([
            '$headerName' => abstract_arg('Defined in FeatureFlagsExtension'),
        ])->call('setRequestStack', [service('request_stack')->nullOnInvalid()])
        ->set($strategyPrefix.'request_query', RequestStackQueryStrategy::class)->abstract()->args([
            '$queryParameterName' => abstract_arg('Defined in FeatureFlagsExtension'),
        ])->call('setRequestStack', [service('request_stack')->nullOnInvalid()])
        ->set($strategyPrefix.'priority', PriorityStrategy::class)->abstract()->args([
            '$strategies' => abstract_arg('Defined in FeatureFlagsExtension'),
        ])
        ->set($strategyPrefix.'affirmative', AffirmativeStrategy::class)->abstract()->args([
            '$strategies' => abstract_arg('Defined in FeatureFlagsExtension'),
        ])
        ->set($strategyPrefix.'unanimous', UnanimousStrategy::class)->abstract()->args([
            '$strategies' => abstract_arg('Defined in FeatureFlagsExtension'),
        ])

        ->set('console.command.feature_flags_debug', FeatureFlagsDebugCommand::class)
            ->args([
                tagged_iterator('feature_flags.feature_provider', 'name'),
            ])
            ->tag('console.command')
    ;
};
