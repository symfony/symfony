<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureFlagsBundle\Debug;

use Symfony\Bundle\FeatureFlagsBundle\DataCollector\FeatureCheckerDataCollector;
use Symfony\Component\FeatureFlags\Strategy\StrategyInterface;
use Symfony\Component\FeatureFlags\StrategyResult;

final class TraceableStrategy implements StrategyInterface
{
    public function __construct(
        private readonly StrategyInterface $strategy,
        private readonly string $strategyId,
        private readonly FeatureCheckerDataCollector $dataCollector,
    ) {
    }

    public function compute(): StrategyResult
    {
        $this->dataCollector->collectComputeStart($this->strategyId, $this->strategy::class);

        $result = $this->strategy->compute();

        $this->dataCollector->collectComputeStop($result);

        return $result;
    }
}
