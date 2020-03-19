<?php

namespace Symfony\Component\HttpKernel\ControllerConfiguration\Configuration;

use Symfony\Component\HttpKernel\ControllerConfiguration\ConfigurationInterface;

/**
 * @Annotation
 */
class QueryParam extends ConfigurationAnnotation
{
    private $name;

    public function getName()
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setValue(string $value): void
    {
        $this->setName($value);
    }

    public function getTarget(): array
    {
        return [ConfigurationInterface::TARGET_CLASS, ConfigurationInterface::TARGET_ARGUMENTS];
    }
}
