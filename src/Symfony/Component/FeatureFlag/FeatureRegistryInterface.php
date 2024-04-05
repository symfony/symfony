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

use Symfony\Component\FeatureFlag\Exception\FeatureNotFoundException;

interface FeatureRegistryInterface
{
    public function has(string $featureName): bool;

    /**
     * @throws FeatureNotFoundException When the feature is not registered
     */
    public function get(string $featureName): callable;

    /**
     * @return list<string> A list of all registered feature names
     */
    public function getNames(): array;
}
