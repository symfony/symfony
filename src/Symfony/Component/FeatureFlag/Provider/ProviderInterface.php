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

interface ProviderInterface
{
    public function has(string $featureName): bool;

    /**
     * @return \Closure(): mixed
     */
    public function get(string $featureName): \Closure;

    /**
     * @return list<string>
     */
    public function getNames(): array;
}
