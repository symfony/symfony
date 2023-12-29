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

interface FeatureRegistryInterface
{
    public function get(string $featureName): callable;

    /**
     * @return array<string> An array of all registered feature names.
     */
    public function getNames(): array;
}
