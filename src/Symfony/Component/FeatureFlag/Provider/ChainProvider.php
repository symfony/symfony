<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag\Provider;

/**
 * @experimental
 */
final class ChainProvider implements ProviderInterface
{
    public function __construct(
        /** @var iterable<ProviderInterface> */
        private readonly iterable $providers = [],
    ) {
    }

    public function get(string $featureName): ?callable
    {
        foreach ($this->providers as $provider) {
            if ($feature = $provider->get($featureName)) {
                return $feature;
            }
        }

        return null;
    }
}
