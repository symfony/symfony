<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlags;

final class FeatureChecker implements FeatureCheckerInterface
{
    public function __construct(
        private readonly FeatureCollection $features,
        private readonly bool $whenNotFound,
    ) {
    }

    public function isEnabled(string $featureName): bool
    {
        if (!$this->features->has($featureName)) {
            return $this->whenNotFound;
        }

        return $this->features->get($featureName)->isEnabled();
    }
}
