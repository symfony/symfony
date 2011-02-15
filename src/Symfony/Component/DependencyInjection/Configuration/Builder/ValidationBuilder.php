<?php

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

/**
 * This class builds validation conditions.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class ValidationBuilder
{
    public $parent;
    public $rules;

    /**
     * Constructor
     *
     * @param Symfony\Component\DependencyInjection\Configuration\Builder\NodeBuilder $parent
     */
    public function __construct($parent)
    {
        $this->parent = $parent;

        $this->rules = array();
    }

    /**
     * Registers a closure to run as normalization or an expression builder to build it if null is provided.
     *
     * @param \Closure $closure
     *
     * @return Symfony\Component\DependencyInjection\Configuration\Builder\ExprBuilder|Symfony\Component\DependencyInjection\Configuration\Builder\ValidationBuilder
     */
    public function rule(\Closure $closure = null)
    {
        if (null !== $closure) {
            $this->rules[] = $closure;

            return $this;
        }

        return $this->rules[] = new ExprBuilder($this->parent);
    }
}