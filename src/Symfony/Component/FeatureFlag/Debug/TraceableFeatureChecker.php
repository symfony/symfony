<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag\Debug;

use Symfony\Component\FeatureFlag\FeatureCheckerInterface;

final class TraceableFeatureChecker implements FeatureCheckerInterface
{
    /** @var array<string, bool> */
    private array $checks = [];
    /** @var array<string, mixed> */
    private array $values = [];

    public function __construct(
        private readonly FeatureCheckerInterface $decorated,
    ) {
    }

    public function isEnabled(string $featureName, mixed $expectedValue = true): bool
    {
        $isEnabled = $this->checks[$featureName] = $this->decorated->isEnabled($featureName, $expectedValue);
        // Force logging value. It has no cost since value is cached by decorated FeatureChecker.
        $this->getValue($featureName);

        return $isEnabled;
    }

    public function getValue(string $featureName): mixed
    {
        return $this->values[$featureName] = $this->decorated->getValue($featureName);
    }

    public function getChecks(): array
    {
        return $this->checks;
    }

    public function getValues(): array
    {
        return $this->values;
    }
}
