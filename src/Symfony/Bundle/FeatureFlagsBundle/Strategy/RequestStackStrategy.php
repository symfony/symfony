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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class RequestStackStrategy implements StrategyInterface
{
    private RequestStack|null $requestStack = null;

    public function setRequestStack(RequestStack|null $requestStack = null): void
    {
        $this->requestStack = $requestStack;
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

        if (($result = $this->computeRequest($currentRequest)) !== StrategyResult::Abstain) {
            return $result;
        }

        $mainRequest = $this->requestStack->getMainRequest();

        if (null === $mainRequest) {
            return StrategyResult::Abstain;
        }

        return $this->computeRequest($mainRequest);
    }

    abstract protected function computeRequest(Request $request): StrategyResult;
}
