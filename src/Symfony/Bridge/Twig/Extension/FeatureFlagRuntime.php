<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\FeatureFlag\FeatureCheckerInterface;

final class FeatureFlagRuntime
{
    public function __construct(private readonly ?FeatureCheckerInterface $featureEnabledChecker = null)
    {
    }

    public function isFeatureEnabled(string $featureName, mixed $expectedValue = true): bool
    {
        if (null === $this->featureEnabledChecker) {
            throw new \LogicException(sprintf('An instance of "%s" must be provided to use "%s()".', FeatureCheckerInterface::class, __METHOD__));
        }

        return $this->featureEnabledChecker->isEnabled($featureName, $expectedValue);
    }

    public function getFeatureValue(string $featureName): mixed
    {
        if (null === $this->featureEnabledChecker) {
            throw new \LogicException(sprintf('An instance of "%s" must be provided to use "%s()".', FeatureCheckerInterface::class, __METHOD__));
        }

        return $this->featureEnabledChecker->getValue($featureName);
    }
}
