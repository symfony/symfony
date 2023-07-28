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

final class RandomStrategy implements StrategyInterface
{
    public function compute(): StrategyResult
    {
        return match(random_int(0, 2)) {
            0 => StrategyResult::Grant,
            1 => StrategyResult::Abstain,
            2 => StrategyResult::Deny,
        };
    }
}
