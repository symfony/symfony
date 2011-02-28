<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Builder;

/**
 * This class builds an if expression.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class ExprBuilder
{
    public $parent;
    public $ifPart;
    public $thenPart;

    /**
     * Constructor
     *
     * @param NodeBuilder $parent The parent node
     */
    public function __construct(NodeBuilder $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Mark the expression as being always used.
     *
     * @return ExprBuilder
     */
    public function always()
    {
        $this->ifPart = function($v) { return true; };

        return $this;
    }

    /**
     * Sets a closure to use as tests.
     *
     * The default one tests if the value is true.
     *
     * @param \Closure $closure
     * @return ExprBuilder
     */
    public function ifTrue(\Closure $closure = null)
    {
        if (null === $closure) {
            $closure = function($v) { return true === $v; };
        }

        $this->ifPart = $closure;

        return $this;
    }

    /**
     * Tests if the value is a string.
     *
     * @return ExprBuilder
     */
    public function ifString()
    {
        $this->ifPart = function($v) { return is_string($v); };

        return $this;
    }

    /**
     * Tests if the value is null.
     *
     * @return ExprBuilder
     */
    public function ifNull()
    {
        $this->ifPart = function($v) { return null === $v; };

        return $this;
    }

    /**
     * Tests if the value is an array.
     *
     * @return ExprBuilder
     */
    public function ifArray()
    {
        $this->ifPart = function($v) { return is_array($v); };

        return $this;
    }

    /**
     * Tests if the value is in an array.
     *
     * @param array $array
     *
     * @return ExprBuilder
     */
    public function ifInArray(array $array)
    {
        $this->ifPart = function($v) use ($array) { return in_array($v, $array, true); };

        return $this;
    }

    /**
     * Tests if the value is not in an array.
     *
     * @param array $array
     *
     * @return ExprBuilder
     */
    public function ifNotInArray(array $array)
    {
        $this->ifPart = function($v) use ($array) { return !in_array($v, $array, true); };

        return $this;
    }

    /**
     * Sets the closure to run if the test pass.
     *
     * @param \Closure $closure
     *
     * @return ExprBuilder
     */
    public function then(\Closure $closure)
    {
        $this->thenPart = $closure;

        return $this;
    }

    /**
     * Sets a closure returning an empty array.
     *
     * @return ExprBuilder
     */
    public function thenEmptyArray()
    {
        $this->thenPart = function($v) { return array(); };

        return $this;
    }

    /**
     * Sets a closure marking the value as invalid at validation time.
     *
     * if you want to add the value of the node in your message just use a %s placeholder.
     *
     * @param string $message
     *
     * @return ExprBuilder
     */
    public function thenInvalid($message)
    {
        $this->thenPart = function ($v) use ($message) {throw new \InvalidArgumentException(sprintf($message, json_encode($v))); };

        return $this;
    }

    /**
     * Sets a closure unsetting this key of the array at validation time.
     *
     * @return ExprBuilder
     */
    public function thenUnset()
    {
        $this->thenPart = function ($v) { throw new UnsetKeyException('Unsetting key'); };

        return $this;
    }

    /**
     * Returns the parent node
     *
     * @return NodeBuilder
     */
    public function end()
    {
        if (null === $this->ifPart) {
            throw new \RuntimeException('You must specify an if part.');
        }
        if (null === $this->thenPart) {
            throw new \RuntimeException('You must specify a then part.');
        }

        return $this->parent;
    }
}