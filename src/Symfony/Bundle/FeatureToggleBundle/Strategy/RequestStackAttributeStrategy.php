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

use Symfony\Component\FeatureToggle\Strategy\StrategyInterface;
use Symfony\Component\FeatureToggle\StrategyResult;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestStackAttributeStrategy implements StrategyInterface
{
    public function __construct(
        private readonly string $attributeName,
        private readonly RequestStack|null $requestStack = null,
    ) {
    }

    public function compute(): StrategyResult
    {
        if (null === $this->requestStack) {
            return StrategyResult::Abstain;
        }

        $currentRequest = $this->requestStack->getCurrentRequest();

        if (null === $currentRequest) {
            return StrategyResult::Abstain;
        }

        if ($currentRequest->attributes->has($this->attributeName) === false) {
            return StrategyResult::Abstain;
        }

        return $currentRequest->attributes->getBoolean($this->attributeName) ? StrategyResult::Grant : StrategyResult::Deny;
    }
}
