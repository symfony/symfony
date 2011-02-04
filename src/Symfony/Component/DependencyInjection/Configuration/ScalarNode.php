<?php

namespace Symfony\Component\DependencyInjection\Configuration;

use Symfony\Component\DependencyInjection\Configuration\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Configuration\Exception\InvalidTypeException;

/**
 * This node represents a scalar value in the config tree.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ScalarNode extends BaseNode implements PrototypeNodeInterface
{
    protected $defaultValueSet = false;
    protected $defaultValue;
    protected $allowEmptyValue = true;

    public function setDefaultValue($value)
    {
        $this->defaultValueSet = true;
        $this->defaultValue = $value;
    }

    public function hasDefaultValue()
    {
        return $this->defaultValueSet;
    }

    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    public function setAllowEmptyValue($boolean)
    {
        $this->allowEmptyValue = (Boolean) $boolean;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    protected function validateType($value)
    {
        if (!is_scalar($value)) {
            throw new InvalidTypeException(sprintf(
                'Invalid type for path "%s". Expected scalar, but got %s.',
                $this->getPath(),
                json_encode($value)
            ));
        }
    }

    protected function finalizeValue($value)
    {
        if (!$this->allowEmptyValue && empty($value)) {
            throw new InvalidConfigurationException(sprintf(
                'The path "%s" cannot contain an empty value, but got %s.',
                $this->getPath(),
                json_encode($value)
            ));
        }

        return $value;
    }

    protected function normalizeValue($value)
    {
        return $value;
    }

    protected function mergeValues($leftSide, $rightSide)
    {
        return $rightSide;
    }
}