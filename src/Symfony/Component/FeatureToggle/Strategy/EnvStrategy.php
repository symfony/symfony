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

final class EnvStrategy implements StrategyInterface
{
    public function __construct(
        private readonly string $envName,
    ) {
    }

    public function compute(): StrategyResult
    {
        $value = getenv($this->envName);

        if (false === $value) {
            return StrategyResult::Abstain;
        }

        return filter_var($value, \FILTER_VALIDATE_BOOL) ? StrategyResult::Grant : StrategyResult::Deny;
    }
}
