<?php

namespace Symfony\Component\HttpKernel\ControllerConfiguration;

class ConfigurationList implements \Countable, \IteratorAggregate
{
    private $configurations = [];

    public function __construct(array $configurations = [])
    {
        foreach ($configurations as $configuration) {
            $this->add($configuration);
        }
    }

    public function add(ConfigurationInterface $configuration): self
    {
        $this->configurations[] = $configuration;

        return $this;
    }

    public function filter(callable $filter): self
    {
        return new static(array_filter($this->configurations, $filter));
    }

    public function first(): ?ConfigurationInterface
    {
        return reset($this->configurations) ?? null;
    }

    public function count(): int
    {
        return \count($this->configurations);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->configurations);
    }
}
