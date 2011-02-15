<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

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
     * @param Symfony\Component\DependencyInjection\Configuration\Builder\NodeBuilder $parent The parent node
     */
    public function __construct($parent)
    {
        $this->parent = $parent;
    }

    /**
     * Mark the expression as being always used.
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder
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
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder
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
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder
     */
    public function ifString()
    {
        $this->ifPart = function($v) { return is_string($v); };

        return $this;
    }

    /**
     * Tests if the value is null.
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder
     */
    public function ifNull()
    {
        $this->ifPart = function($v) { return null === $v; };

        return $this;
    }

    /**
     * Tests if the value is an array.
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder
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
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder
     */
    public function ifInArray(array $array)
    {
        $this->ifPart = function($v) use ($array) { return in_array($v, $array, true); };

        return $this;
    }

    /**
     * Sets the closure to run if the test pass.
     *
     * @param \Closure $closure
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder
     */
    public function then(\Closure $closure)
    {
        $this->thenPart = $closure;

        return $this;
    }

    /**
     * Sets a closure returning an empty array.
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder
     */
    public function thenEmptyArray()
    {
        $this->thenPart = function($v) { return array(); };

        return $this;
    }

    /**
     * Sets a closure marking the value as invalid at validation time.
     *
     * @param string $message
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder
     */
    public function thenInvalid($message)
    {
        $this->thenPart = function ($v) use ($message) {throw new \InvalidArgumentException($message); };

        return $this;
    }

    /**
     * Sets a closure unsetting this key of the array at validation time.
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder
     */
    public function thenUnset()
    {
        $this->thenPart = function ($v) { throw new UnsetKeyException('Unsetting key'); };

        return $this;
    }

    /**
     * Returns the parent node
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\NodeBuilder
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