<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureFlagsBundle\Twig;

use Symfony\Component\FeatureFlags\FeatureCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FeatureEnabledExtension extends AbstractExtension
{
    public function __construct(
        private readonly FeatureCheckerInterface $featureEnabledChecker,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_feature_enabled', $this->featureEnabledChecker->isEnabled(...)),
        ];
    }
}
