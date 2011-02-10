<?php

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

/**
 * This class builds an if expression.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
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