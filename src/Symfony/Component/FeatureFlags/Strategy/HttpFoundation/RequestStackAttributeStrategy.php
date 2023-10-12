<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlags\Strategy\HttpFoundation;

use Symfony\Component\FeatureFlags\StrategyResult;
use Symfony\Component\HttpFoundation\Request;

final class RequestStackAttributeStrategy extends RequestStackStrategy
{
    public function __construct(
        private readonly string $attributeName,
    ) {
    }

    protected function computeRequest(Request $request): StrategyResult
    {
        if (false === $request->attributes->has($this->attributeName)) {
            return StrategyResult::Abstain;
        }

        return $request->attributes->getBoolean($this->attributeName) ? StrategyResult::Grant : StrategyResult::Deny;
    }
}
