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
final class RequestHeaderStrategy implements StrategyInterface
{
    public function __construct(
        private readonly string $headerName,
    ) {
    }

    public function compute(): StrategyResult
    {
        if (!array_key_exists('HTTP_'.$this->headerName, $_SERVER)) {
            return StrategyResult::Abstain;
        }

        return filter_var($_SERVER['HTTP_'.$this->headerName], \FILTER_VALIDATE_BOOL) ? StrategyResult::Grant : StrategyResult::Deny;
    }
}
