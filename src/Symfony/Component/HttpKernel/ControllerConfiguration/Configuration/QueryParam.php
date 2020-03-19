<?php

namespace Symfony\Component\HttpKernel\ControllerConfiguration\Configuration;

/**
 * @Annotation
 */
class QueryParam extends ConfigurationAnnotation
{
    private $argumentName;
    private $name;

    public function __construct(array $values)
    {
        parent::__construct($values);

        if (null === $this->name) {
            $this->name = $this->argumentName;
        }
    }

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
        $this->setArgumentName($value);
    }

    public function setArgumentName(string $argumentName): void
    {
        $this->argumentName = $argumentName;
    }

    public function getArgumentName()
    {
        return $this->argumentName;
    }
}
