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
    public function __construct(private readonly ?FeatureCheckerInterface $featureChecker = null)
    {
    }

    public function isEnabled(string $featureName, mixed $expectedValue = true): bool
    {
        if (null === $this->featureChecker) {
            throw new \LogicException(sprintf('An instance of "%s" must be provided to use "%s()".', FeatureCheckerInterface::class, __METHOD__));
        }

        return $this->featureChecker->isEnabled($featureName, $expectedValue);
    }

    public function getValue(string $featureName): mixed
    {
        if (null === $this->featureChecker) {
            throw new \LogicException(sprintf('An instance of "%s" must be provided to use "%s()".', FeatureCheckerInterface::class, __METHOD__));
        }

        return $this->featureChecker->getValue($featureName);
    }
}
