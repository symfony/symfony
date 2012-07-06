<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\NumericNode;

/**
 * Abstract class that contain common code of integer and float node definition.
 *
 * @author David Jeanmonod <david.jeanmonod@gmail.com>
 */
abstract class NumericNodeDefinition extends ScalarNodeDefinition
{

    protected $min;
    protected $max;

    /**
     * Ensure the value is smaller than the given reference
     *
     * @param mixed $max
     *
     * @return NumericNodeDefinition
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
     * @return NumericNodeDefinition
     */
    public function min($min)
    {
        if (isset($this->max) && $this->max < $min) {
            throw new \InvalidArgumentException(sprintf('You cannot define a min(%s) as you already have a max(%s)', $min, $this->max));
        }
        $this->min = $min;
        return $this;
    }
}
