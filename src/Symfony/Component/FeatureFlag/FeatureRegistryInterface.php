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

use Psr\Container\ContainerInterface;
use Symfony\Component\FeatureFlag\Exception\FeatureNotFoundException;

interface FeatureRegistryInterface extends ContainerInterface
{
    public function has(string $id): bool;

    /**
     * @throws FeatureNotFoundException When the feature is not registered
     */
    public function get(string $id): callable;

    /**
     * @return array<string> An array of all registered feature names
     */
    public function getNames(): array;
}
