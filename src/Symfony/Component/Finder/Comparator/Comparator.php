<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Comparator;

/**
 * Comparator.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class Comparator
{
    private $target;
    private $operator = '==';

    /**
     * Gets the target value.
     *
     * @return string The target value
     *
     * @since v2.0.0
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Sets the target value.
     *
     * @param string $target The target value
     *
     * @since v2.0.0
     */
    public function setTarget($target)
    {
        $this->target = $target;
    }

    /**
     * Gets the comparison operator.
     *
     * @return string The operator
     *
     * @since v2.0.0
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Sets the comparison operator.
     *
     * @param string $operator A valid operator
     *
     * @throws \InvalidArgumentException
     *
     * @since v2.0.0
     */
    public function setOperator($operator)
    {
        if (!$operator) {
            $operator = '==';
        }

        if (!in_array($operator, array('>', '<', '>=', '<=', '==', '!='))) {
            throw new \InvalidArgumentException(sprintf('Invalid operator "%s".', $operator));
        }

        $this->operator = $operator;
    }

    /**
     * Tests against the target.
     *
     * @param mixed $test A test value
     *
     * @return Boolean
     *
     * @since v2.0.0
     */
    public function test($test)
    {
        switch ($this->operator) {
            case '>':
                return $test > $this->target;
            case '>=':
                return $test >= $this->target;
            case '<':
                return $test < $this->target;
            case '<=':
                return $test <= $this->target;
            case '!=':
                return $test != $this->target;
        }

        return $test == $this->target;
    }
}
