<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureToggle\Strategy;

use Symfony\Component\FeatureToggle\StrategyResult;

final class UnanimousStrategy implements StrategyInterface
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

            if (StrategyResult::Deny === $innerResult) {
                return StrategyResult::Deny;
            }

            if (StrategyResult::Grant === $innerResult) {
                $result = StrategyResult::Grant;
            }
        }

        return $result;
    }
}
