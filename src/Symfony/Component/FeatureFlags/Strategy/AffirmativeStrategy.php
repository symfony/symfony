<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlags\Strategy;

use Symfony\Component\FeatureFlags\StrategyResult;
use function iterator_to_array;

final class AffirmativeStrategy implements StrategyInterface, OuterStrategiesInterface
{
    /**
     * @param iterable<StrategyInterface> $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
    ) {
    }

    public function compute(): StrategyResult
    {
        $result = StrategyResult::Abstain;
        foreach ($this->strategies as $strategy) {
            $innerResult = $strategy->compute();

            if (StrategyResult::Grant === $innerResult) {
                return StrategyResult::Grant;
            }

            if (StrategyResult::Deny === $innerResult) {
                $result = StrategyResult::Deny;
            }
        }

        return $result;
    }

    public function getInnerStrategies(): array
    {
        return iterator_to_array($this->strategies);
    }
}
