<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureToggle;

use Psr\Container\ContainerInterface;
use function array_key_exists;
use function array_shift;
use function is_callable;

/** @implements \IteratorAggregate<int, Feature> */
final class FeatureCollection implements ContainerInterface, \IteratorAggregate
{
    /** @var array<string, Feature> */
    private array $features = [];

    /** @var array<iterable<Feature>|(\Closure(): iterable<Feature>)> */
    private array $featureProviders = [];

    /**
     * @param iterable<Feature> $features
     */
    public function __construct(iterable $features)
    {
        $this->append($features);
    }

    /**
     * @param iterable<Feature>|(\Closure(): iterable<Feature>) $features
     */
    private function append(iterable|\Closure $features): void
    {
        $this->featureProviders[] = $features;
    }

    /**
     * @param iterable<Feature>|(\Closure(): iterable<Feature>) $features
     */
    public function withFeatures(iterable|\Closure $features): self
    {
        $this->append($features);

        return $this;
    }

    private function findFeature(string $featureName): ?Feature
    {
        if (array_key_exists($featureName, $this->features)) {
            return $this->features[$featureName];
        }

        while (($featureProvider = array_shift($this->featureProviders)) !== null) {
            if (is_callable($featureProvider)) {
                $featureProvider = $featureProvider();
            }

            foreach ($featureProvider as $feature) {
                $this->features[$feature->getName()] = $feature;
            }

            if (array_key_exists($featureName, $this->features)) {
                return $this->features[$featureName];
            }
        }

        return null;
    }

    public function has(string $id): bool
    {
        return $this->findFeature($id) !== null;
    }

    /**
     * @throws FeatureNotFoundException If the feature is not registered in this provider.
     */
    public function get(string $id): Feature
    {
        return $this->findFeature($id) ?? throw new FeatureNotFoundException($id);
    }

    /**
     * @return \Traversable<int, Feature>
     */
    public function getIterator(): \Traversable
    {
        $this->findFeature('');

        return new \ArrayIterator(array_values($this->features));
    }
}
