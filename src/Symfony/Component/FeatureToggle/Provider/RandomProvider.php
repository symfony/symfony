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
use Symfony\Component\FeatureToggle\Strategy\StrategyInterface;

final class RandomProvider implements ProviderInterface
{
    public function __construct(
        private readonly StrategyInterface $defaultStrategy,
    ) {
    }

    public function provide(): FeatureCollection
    {
        $features = [];
        for ($i = 1; $i <= random_int(2, 10); $i++) {
            $features[] = new Feature(
                name: "random-feature-$i",
                description: "Random feature #$i",
                default: (bool) random_int(0, 1),
                strategy: $this->defaultStrategy,
            );
        }

        return new FeatureCollection($features);
    }
}
