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

use Symfony\Component\FeatureFlag\Provider\ProviderInterface;

/**
 * Checks if a feature is enabled or retrieves its value.
 *
 * This is the main entry point to interact with the feature flag
 * system. It uses the configured {@see ProviderInterface} to determine
 * whether a feature is active and what its current value is.
 *
 * @experimental
 */
interface FeatureCheckerInterface
{
    public function isEnabled(string $featureName): bool;

    public function getValue(string $featureName): mixed;
}
