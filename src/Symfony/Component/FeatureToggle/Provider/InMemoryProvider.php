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
use function array_keys;
use function array_reduce;

final class InMemoryProvider implements ProviderInterface
{
    /**
     * @var array<string, Feature> $features
     */
    private readonly array $features;

    /**
     * @param list<Feature> $features
     */
    public function __construct(
        array $features,
    ) {
        $this->features = array_reduce($features, static function (array $features, Feature $feature): array {
            $features[$feature->getName()] = $feature;

            return $features;
        }, []);
    }

    public function get(string $featureName): ?Feature
    {
        return $this->features[$featureName] ?? null;
    }

    public function names(): array
    {
        return array_keys($this->features);
    }
}
