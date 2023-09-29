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

use Symfony\Component\FeatureFlags\StrategyResult;
use Symfony\Component\HttpFoundation\Request;

final class RequestStackQueryStrategy extends RequestStackStrategy
{
    public function __construct(
        private readonly string $queryParameterName,
    ) {
    }

    protected function computeRequest(Request $request): StrategyResult
    {
        if (false === $request->query->has($this->queryParameterName)) {
            return StrategyResult::Abstain;
        }

        return $request->query->getBoolean($this->queryParameterName) ? StrategyResult::Grant : StrategyResult::Deny;
    }
}
