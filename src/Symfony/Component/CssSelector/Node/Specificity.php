<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Node;

/**
 * Represents a node specificity.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @see http://www.w3.org/TR/selectors/#specificity
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Specificity
{
    const A_FACTOR = 100;
    const B_FACTOR = 10;
    const C_FACTOR = 1;

    /**
     * @var int
     */
    private $a;

    /**
     * @var int
     */
    private $b;

    /**
     * @var int
     */
    private $c;

    /**
     * @param int $a
     * @param int $b
     * @param int $c
     */
    public function __construct($a, $b, $c)
    {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
    }

    /**
     * @param Specificity $specificity
     *
     * @return self
     */
    public function plus(Specificity $specificity)
    {
        return new self($this->a + $specificity->a, $this->b + $specificity->b, $this->c + $specificity->c);
    }

    /**
     * Returns global specificity value.
     *
     * @return int
     */
    public function getValue()
    {
        return $this->a * self::A_FACTOR + $this->b * self::B_FACTOR + $this->c * self::C_FACTOR;
    }

    /**
     * Returns -1 if the object specificity is lower than the argument,
     * 0 if they are equal, and 1 if the argument is lower.
     *
     * @param Specificity $specificity
     *
     * @return int
     */
    public function compareTo(Specificity $specificity)
    {
        if ($this->a !== $specificity->a) {
            return $this->a > $specificity->a ? 1 : -1;
        }

        if ($this->b !== $specificity->b) {
            return $this->b > $specificity->b ? 1 : -1;
        }

        if ($this->c !== $specificity->c) {
            return $this->c > $specificity->c ? 1 : -1;
        }

        return 0;
    }
}
