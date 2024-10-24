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

use Symfony\Component\FeatureFlag\Provider\ProviderInterface;

/**
 * @experimental
 */
final class FeatureChecker implements FeatureCheckerInterface
{
    private array $cache = [];

    public function __construct(
        private readonly ProviderInterface $provider,
    ) {
    }

    public function isEnabled(string $featureName, mixed $expectedValue = true): bool
    {
        return $this->getValue($featureName) === $expectedValue;
    }

    public function getValue(string $featureName): mixed
    {
        return $this->cache[$featureName] ??= $this->provider->get($featureName)();
    }
}
