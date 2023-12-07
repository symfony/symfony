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

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FeatureFlagExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_feature_enabled', [FeatureFlagRuntime::class, 'isFeatureEnabled']),
            new TwigFunction('get_feature_value', [FeatureFlagRuntime::class, 'getFeatureValue']),
        ];
    }
}
