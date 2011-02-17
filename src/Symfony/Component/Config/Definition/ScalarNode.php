<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * This node represents a scalar value in the config tree.
 *
 * The following values are considered scalars:
 *   * booleans
 *   * strings
 *   * null
 *   * integers
 *   * floats
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ScalarNode extends BaseNode implements PrototypeNodeInterface
{
    protected $defaultValueSet = false;
    protected $defaultValue;
    protected $allowEmptyValue = true;

    /**
     * {@inheritDoc}
     */
    public function setDefaultValue($value)
    {
        $this->defaultValueSet = true;
        $this->defaultValue = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function hasDefaultValue()
    {
        return $this->defaultValueSet;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * Sets if this node is allowed to have an empty value.
     *
     * @param boolean $boolean True if this entity will accept empty values.
     * @return void
     */
    public function setAllowEmptyValue($boolean)
    {
        $this->allowEmptyValue = (Boolean) $boolean;
    }

    /**
     * {@inheritDoc}
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritDoc}
     */
    protected function validateType($value)
    {
        if (!is_scalar($value) && null !== $value) {
            throw new InvalidTypeException(sprintf(
                'Invalid type for path "%s". Expected scalar, but got %s.',
                $this->getPath(),
                json_encode($value)
            ));
        }
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    protected function normalizeValue($value)
    {
        return $value;
    }

    /**
     * {@inheritDoc}
     */
    protected function mergeValues($leftSide, $rightSide)
    {
        return $rightSide;
    }
}