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

use Symfony\Component\Config\Definition\VariableNode;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;


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
class ScalarNode extends VariableNode
{
    protected $min;
    protected $max;

    /**
     * {@inheritDoc}
     */
    protected function validateType($value)
    {
        if (!is_scalar($value) && null !== $value) {
            $ex = new InvalidTypeException(sprintf(
                'Invalid type for path "%s". Expected scalar, but got %s.',
                $this->getPath(),
                gettype($value)
            ));
            $ex->setPath($this->getPath());

            throw $ex;
        }
    }

    /**
     * Ensure the value is smaller than the given reference
     *
     * @param mixed $max
     *
     * @return ScalarNode
     */
    public function max($max)
    {
        if (isset($this->min) && $this->min > $max) {
            throw new \InvalidArgumentException(sprintf('You cannot define a max(%s) as you already have a min(%s)', $max, $this->min));
        }
        $this->max = $max;
        return $this;
    }

    /**
     * Ensure the value is bigger than the given reference
     *
     * @param mixed $min
     *
     * @return ScalarNode
     */
    public function min($min)
    {
        if (isset($this->max) && $this->max < $min) {
            throw new \InvalidArgumentException(sprintf('You cannot define a min(%s) as you already have a max(%s)', $min, $this->max));
        }
        $this->min = $min;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    protected function finalizeValue($value)
    {
        $value = parent::finalizeValue($value);

        $errorMsg = null;
        if (isset($this->min) && $value < $this->min) {
            $errorMsg = sprintf('The value %s is too small for path "%s". Should be greater than: %s', $value, $this->getPath(), $this->min);
        }
        if (isset($this->max) && $value > $this->max) {
            $errorMsg = sprintf('The value %s is too big for path "%s". Should be less than: %s', $value, $this->getPath(), $this->max);
        }
        if (isset($errorMsg)){
            $ex = new InvalidConfigurationException($errorMsg);
            $ex->setPath($this->getPath());
            throw $ex;
        }

        return $value;
    }
}
