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

use Symfony\Component\FeatureFlag\ArgumentResolver\ArgumentResolver;

final class InMemoryProvider implements ProviderInterface
{
    /**
     * @param array<string, (\Closure(): mixed)> $features
     */
    public function __construct(
        private readonly array $features,
    ) {
    }

    public function has(string $featureName): bool
    {
        return \array_key_exists($featureName, $this->features);
    }

    public function get(string $featureName): \Closure
    {
        return $this->features[$featureName] ?? fn() => false;
    }

    public function getNames(): array
    {
        return array_keys($this->features);
    }
}
