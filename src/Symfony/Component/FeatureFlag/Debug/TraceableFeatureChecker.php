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
    /** @var array<string, list<array{expectedValue: mixed, isEnabled: bool, calls: int}>> */
    private array $checks = [];
    /** @var array<string, mixed> */
    private array $resolvedValues = [];
    /** @var array<string, mixed> */
    private array $expectedValues = [];

    public function __construct(
        private readonly FeatureCheckerInterface $decorated,
    ) {
    }

    public function isEnabled(string $featureName, mixed $expectedValue = true): bool
    {
        $isEnabled = $this->decorated->isEnabled($featureName, $expectedValue);

        // Check duplicates
        $this->expectedValues[$featureName] ??= [];
        if (false !== ($i = array_search($expectedValue, $this->expectedValues[$featureName] ?? [], true))) {
            ++$this->checks[$featureName][$i]['calls'];

            return $isEnabled;
        }
        $this->expectedValues[$featureName][] = $expectedValue;

        // Force logging value. It has no cost since value is cached by the decorated FeatureChecker.
        $this->getValue($featureName);

        $this->checks[$featureName] ??= [];
        $this->checks[$featureName][] = ['expectedValue' => $expectedValue, 'isEnabled' => $isEnabled, 'calls' => 1];

        return $isEnabled;
    }

    public function isDisabled(string $featureName, mixed $expectedValue = true): bool
    {
        return !$this->isEnabled($featureName, $expectedValue);
    }

    public function getValue(string $featureName): mixed
    {
        return $this->resolvedValues[$featureName] = $this->decorated->getValue($featureName);
    }

    /**
     * @return array<string, list<array{expectedValue: mixed, isEnabled: bool}>>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResolvedValues(): array
    {
        return $this->resolvedValues;
    }
}
