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

/** @implements \IteratorAggregate<int, Feature> */
final class FeatureCollection implements ContainerInterface, \IteratorAggregate
{
    /** @var array<string, Feature>|null */
    private array|null $features = null;

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
        $this->features = null;
    }

    /**
     * @param iterable<Feature>|(\Closure(): iterable<Feature>) $features
     */
    public function withFeatures(iterable|\Closure $features): self
    {
        $this->append($features);

        return $this;
    }

    /**
     * @phpstan-assert-if-true null $this->features
     */
    private function compile(): void
    {
        if (null !== $this->features) {
            return;
        }

        $this->features = [];

        foreach ($this->featureProviders as $featureProvider) {
            if (is_callable($featureProvider)) {
                $featureProvider = $featureProvider();
            }

            foreach ($featureProvider as $feature) {
                $this->features[$feature->getName()] = $feature;
            }
        }
    }

    public function has(string $id): bool
    {
        $this->compile();
        return array_key_exists($id, $this->features);
    }

    /**
     * @throws FeatureNotFoundException If the feature is not registered in this provider.
     */
    public function get(string $id): Feature
    {
        $this->compile();
        return $this->features[$id] ?? throw new FeatureNotFoundException($id);
    }

    /**
     * @return \ArrayIterator<int, Feature>
     */
    public function getIterator(): \ArrayIterator
    {
        $this->compile();

        return new \ArrayIterator(array_values($this->features));
    }
}
