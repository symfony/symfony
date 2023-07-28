<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureToggle;

enum StrategyResult
{
    case Grant;
    case Deny;
    case Abstain;

    public function isEnabled(bool $fallback): bool
    {
        return match($this) {
            self::Grant => true,
            self::Deny => false,
            self::Abstain => $fallback,
        };
    }
}
