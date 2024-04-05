<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag;

use Symfony\Component\FeatureFlag\Exception\FeatureNotFoundException;

final class FeatureRegistry implements FeatureRegistryInterface
{
    /**
     * @param array<string, (\Closure(): mixed)> $features
     */
    public function __construct(private readonly array $features)
    {
    }

    public function has(string $featureName): bool
    {
        return \array_key_exists($featureName, $this->features);
    }

    public function get(string $featureName): callable
    {
        return $this->features[$featureName] ?? throw new FeatureNotFoundException(sprintf('Feature "%s" not found.', $featureName));
    }

    public function getNames(): array
    {
        return array_keys($this->features);
    }
}
