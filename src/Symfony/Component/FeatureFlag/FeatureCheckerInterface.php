<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag;

interface FeatureCheckerInterface
{
    public function isEnabled(string $featureName, mixed $expectedValue = true): bool;

    public function getValue(string $featureName): mixed;
}
