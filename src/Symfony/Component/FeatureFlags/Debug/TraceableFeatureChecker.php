<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlags\Debug;

use Symfony\Component\FeatureFlags\DataCollector\FeatureCheckerDataCollector;
use Symfony\Component\FeatureFlags\FeatureCheckerInterface;

final class TraceableFeatureChecker implements FeatureCheckerInterface
{
    public function __construct(
        private readonly FeatureCheckerInterface $featureChecker,
        private readonly FeatureCheckerDataCollector $dataCollector,
    ) {
    }

    public function isEnabled(string $featureName): bool
    {
        $this->dataCollector->collectIsEnabledStart($featureName);

        $result = $this->featureChecker->isEnabled($featureName);

        $this->dataCollector->collectIsEnabledStop($result);

        return $result;
    }
}
