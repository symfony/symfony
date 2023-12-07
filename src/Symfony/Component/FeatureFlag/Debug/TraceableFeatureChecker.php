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

/**
 * @experimental
 */
final class TraceableFeatureChecker implements FeatureCheckerInterface
{
    /** @var array<string, array{status: 'resolved'|'enabled'|'disabled', value: mixed, calls: int}> */
    private array $resolvedValues = [];

    public function __construct(
        private readonly FeatureCheckerInterface $decorated,
    ) {
    }

    public function isEnabled(string $featureName): bool
    {
        $isEnabled = $this->decorated->isEnabled($featureName);

        // Force logging value. It has no cost since value is cached by the decorated FeatureChecker.
        $this->getValue($featureName);

        $this->resolvedValues[$featureName]['status'] = $isEnabled ? 'enabled' : 'disabled';

        return $isEnabled;
    }

    public function getValue(string $featureName): mixed
    {
        $value = $this->decorated->getValue($featureName);

        $this->resolvedValues[$featureName] ??= [
            'status' => 'resolved',
            'value' => $value,
            'calls' => 0,
        ];

        ++$this->resolvedValues[$featureName]['calls'];

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResolvedValues(): array
    {
        return $this->resolvedValues;
    }
}
