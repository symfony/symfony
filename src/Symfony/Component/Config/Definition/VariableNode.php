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
     * @return void
     */
    public function setDefaultValue(mixed $value)
    {
        $this->defaultValueSet = true;
        $this->defaultValue = $value;
    }

    public function hasDefaultValue(): bool
    {
        return $this->defaultValueSet;
    }

    public function getDefaultValue(): mixed
    {
        $v = $this->defaultValue;

        return $v instanceof \Closure ? $v() : $v;
    }

    /**
     * Sets if this node is allowed to have an empty value.
     *
     * @param bool $boolean True if this entity will accept empty values
     *
     * @return void
     */
    public function setAllowEmptyValue(bool $boolean)
    {
        $this->allowEmptyValue = $boolean;
    }

    /**
     * @return void
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return void
     */
    protected function validateType(mixed $value)
    {
    }

    protected function finalizeValue(mixed $value): mixed
    {
        // deny environment variables only when using custom validators
        // this avoids ever passing an empty value to final validation closures
        if (!$this->allowEmptyValue && $this->isHandlingPlaceholder() && $this->finalValidationClosures) {
            $e = new InvalidConfigurationException(sprintf('The path "%s" cannot contain an environment variable when empty values are not allowed by definition and are validated.', $this->getPath()));
            if ($hint = $this->getInfo()) {
                $e->addHint($hint);
            }
            $e->setPath($this->getPath());

            throw $e;
        }

        if (!$this->allowEmptyValue && $this->isValueEmpty($value)) {
            $ex = new InvalidConfigurationException(sprintf('The path "%s" cannot contain an empty value, but got %s.', $this->getPath(), json_encode($value)));
            if ($hint = $this->getInfo()) {
                $ex->addHint($hint);
            }
            $ex->setPath($this->getPath());

            throw $ex;
        }

        return $value;
    }

    protected function normalizeValue(mixed $value): mixed
    {
        return $value;
    }

    protected function mergeValues(mixed $leftSide, mixed $rightSide): mixed
    {
        return $rightSide;
    }

    /**
     * Evaluates if the given value is to be treated as empty.
     *
     * By default, PHP's empty() function is used to test for emptiness. This
     * method may be overridden by subtypes to better match their understanding
     * of empty data.
     *
     * @see finalizeValue()
     */
    protected function isValueEmpty(mixed $value): bool
    {
        return empty($value);
    }
}
