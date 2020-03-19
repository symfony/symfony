<?php

namespace Symfony\Component\HttpKernel\ControllerConfiguration;

class ConfigurationList implements \Countable
{
    private $configurations = [];
    private $targetConfigurations = [];

    public function add(ConfigurationInterface $configuration): void
    {
        $this->configurations[] = $configuration;
        foreach ($configuration->getTarget() as $target) {
            if (!isset($this->targetConfigurations[$target])) {
                $this->targetConfigurations[$target] = [];
            }

            $this->targetConfigurations[$target][] = $configuration;
        }
    }

    public function forTarget(string $target): array
    {
        if (!isset($this->targetConfigurations[$target])) {
            return [];
        }

        return $this->targetConfigurations[$target];
    }

    public function count(): int
    {
        return \count($this->configurations);
    }
}
