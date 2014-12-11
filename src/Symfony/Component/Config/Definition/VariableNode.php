<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This node represents a value of variable type in the config tree.
 *
 * This node is intended for values of arbitrary type.
 * Any PHP type is accepted as a value.
 *
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class VariableNode extends BaseNode implements PrototypeNodeInterface
{
    protected $defaultValueSet = false;
    protected $defaultValue;
    protected $allowEmptyValue = true;

    /**
     * {@inheritdoc}
     */
    public function setDefaultValue($value)
    {
        $this->defaultValueSet = true;
        $this->defaultValue = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function hasDefaultValue()
    {
        return $this->defaultValueSet;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValue()
    {
        $v = $this->defaultValue;

        return $v instanceof \Closure ? $v() : $v;
    }

    /**
     * Sets if this node is allowed to have an empty value.
     *
     * @param bool $boolean True if this entity will accept empty values.
     */
    public function setAllowEmptyValue($boolean)
    {
        $this->allowEmptyValue = (bool) $boolean;
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    protected function validateType($value)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function finalizeValue($value)
    {
        if (!$this->allowEmptyValue && empty($value)) {
            $ex = new InvalidConfigurationException(sprintf(
                'The path "%s" cannot contain an empty value, but got %s.',
                $this->getPath(),
                json_encode($value)
            ));
            $ex->setPath($this->getPath());

            throw $ex;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function normalizeValue($value)
    {
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function mergeValues($leftSide, $rightSide)
    {
        return $rightSide;
    }
}
