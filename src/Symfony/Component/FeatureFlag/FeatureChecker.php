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

final class FeatureChecker implements FeatureCheckerInterface
{
    private array $cache = [];

    public function __construct(
        private readonly FeatureRegistry $featureRegistry,
        private readonly mixed $default,
    ) {
    }

    public function isEnabled(string $featureName, mixed $expectedValue = true): bool
    {
        return $this->getValue($featureName) === $expectedValue;
    }

    public function getValue(string $featureName): mixed
    {
        try {
            return $this->cache[$featureName] ??= $this->featureRegistry->get($featureName)();
        } catch (FeatureNotFoundException) {
            return $this->default;
        }
    }
}
