<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureToggle\Provider;

use Symfony\Component\FeatureToggle\Feature;
use Symfony\Component\FeatureToggle\FeatureCollection;

final class InMemoryProvider implements ProviderInterface
{
    /**
     * @param list<Feature> $features
     */
    public function __construct(
        private readonly array $features,
    ) {
    }

    public function provide(): FeatureCollection
    {
        return new FeatureCollection($this->features);
    }
}
