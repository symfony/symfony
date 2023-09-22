<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureToggleBundle\Strategy;

use Symfony\Component\FeatureToggle\StrategyResult;
use Symfony\Component\HttpFoundation\Request;

final class RequestStackHeaderStrategy extends RequestStackStrategy
{
    public function __construct(
        private readonly string $headerName,
    ) {
    }

    protected function computeRequest(Request $request): StrategyResult
    {
        if (false === $request->headers->has($this->headerName)) {
            return StrategyResult::Abstain;
        }

        return filter_var($request->headers->get($this->headerName), \FILTER_VALIDATE_BOOL) ? StrategyResult::Grant : StrategyResult::Deny;
    }
}
