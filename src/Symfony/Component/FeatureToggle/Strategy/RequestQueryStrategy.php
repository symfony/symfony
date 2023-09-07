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

// TODO: make it case insensitive ?
final class RequestQueryStrategy implements StrategyInterface
{
    public function __construct(
        private readonly string $queryParameterName,
    ) {
    }

    public function compute(): StrategyResult
    {
        if (!array_key_exists($this->queryParameterName, $_GET)) {
            return StrategyResult::Abstain;
        }

        return filter_var($_GET[$this->queryParameterName], \FILTER_VALIDATE_BOOL) ? StrategyResult::Grant : StrategyResult::Deny;
    }
}
