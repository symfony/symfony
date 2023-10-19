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

final class GrantStrategy implements StrategyInterface
{
    public function compute(): StrategyResult
    {
        return StrategyResult::Grant;
    }
}
