<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureFlagsBundle\Strategy;

use Symfony\Component\FeatureFlags\Strategy\StrategyInterface;
use Symfony\Component\FeatureFlags\StrategyResult;

final class CustomStrategy implements StrategyInterface
{
    public function __construct(
        private readonly StrategyInterface $inner,
    ) {
    }

    public function compute(): StrategyResult
    {
        return $this->inner->compute();
    }
}
