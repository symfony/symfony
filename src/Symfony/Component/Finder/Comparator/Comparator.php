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
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Comparator
{
    private string $target;
    private string $operator;

    public function __construct(string $target, string $operator = '==')
    {
        if (!\in_array($operator, ['>', '<', '>=', '<=', '==', '!='])) {
            throw new \InvalidArgumentException(sprintf('Invalid operator "%s".', $operator));
        }

        $this->target = $target;
        $this->operator = $operator;
    }

    /**
     * Gets the target value.
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * Gets the comparison operator.
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Tests against the target.
     */
    public function test(mixed $test): bool
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
