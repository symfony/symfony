<?php

declare(strict_types=1);

namespace Symfony\Component\FeatureFlags\Provider;

use Symfony\Component\FeatureFlags\Feature;
use function array_key_exists;
use function array_keys;

final class LazyInMemoryProvider implements ProviderInterface
{
    /**
     * @param array<string, (\Closure(): Feature)> $features
     */
    public function __construct(
        private readonly array $features,
    ) {
    }

    public function get(string $featureName): ?Feature
    {
        if (!array_key_exists($featureName, $this->features)) {
            return null;
        }

        return ($this->features[$featureName])();
    }

    public function names(): array
    {
        return array_keys($this->features);
    }
}
