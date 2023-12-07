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

use Symfony\Component\FeatureFlag\DataCollector\FeatureCheckerDataCollector;
use Symfony\Component\FeatureFlag\FeatureCheckerInterface;

final class TraceableFeatureChecker implements FeatureCheckerInterface
{
    public function __construct(
        private readonly FeatureCheckerInterface $decorated,
        private readonly FeatureCheckerDataCollector $dataCollector,
    ) {
    }

    public function isEnabled(string $featureName, mixed $expectedValue = true): bool
    {
        $isEnabled = $this->decorated->isEnabled($featureName, $expectedValue);

        $this->dataCollector->collectIsEnabled($featureName, $isEnabled);
        $this->dataCollector->collectValue($featureName, $this->decorated->getValue($featureName));

        return $isEnabled;
    }

    public function getValue(string $featureName): mixed
    {
        $value = $this->decorated->getValue($featureName);

        $this->dataCollector->collectValue($featureName, $value);

        return $value;
    }
}
