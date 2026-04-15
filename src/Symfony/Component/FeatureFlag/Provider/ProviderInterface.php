<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag\Provider;

/**
 * Represents a class that provides feature flags.
 *
 * A provider is responsible for retrieving the logic (as a Closure) associated
 * with a feature flag name. This allows the feature flag system to be
 * decoupled from the actual source of the feature flag definitions (e.g.
 * configuration, database, or a remote service).
 *
 * @experimental
 */
interface ProviderInterface
{
    /**
     * @return ?callable(): mixed
     */
    public function get(string $featureName): ?callable;
}
