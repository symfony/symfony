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
    public function __construct(
        private readonly FeatureCheckerInterface $featureChecker,
    ) {
    }

    public function isEnabled(string $featureName): bool
    {
        return $this->featureChecker->isEnabled($featureName);
    }

    public function getValue(string $featureName): mixed
    {
        return $this->featureChecker->getValue($featureName);
    }
}
