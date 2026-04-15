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
final class InMemoryProvider implements ProviderInterface
{
    /**
     * @param array<string, (callable(): mixed)> $features
     */
    public function __construct(
        private readonly array $features,
    ) {
    }

    public function get(string $featureName): ?callable
    {
        return $this->features[$featureName] ?? null;
    }
}
