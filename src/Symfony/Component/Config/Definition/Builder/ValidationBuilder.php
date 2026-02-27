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

/**
 * This class builds validation conditions.
 *
 * @template T of NodeDefinition
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class ValidationBuilder
{
    /**
     * @var (ExprBuilder<T>|\Closure)[]
     */
    public array $rules = [];

    /**
     * @param T $node
     */
    public function __construct(
        protected NodeDefinition $node,
    ) {
    }

    /**
     * Registers a closure to run as normalization or an expression builder to build it if null is provided.
     *
     * @return ($closure is \Closure ? $this : ExprBuilder<T>)
     */
    public function rule(?\Closure $closure = null): ExprBuilder|static
    {
        if ($closure) {
            $this->rules[] = $closure;

            return $this;
        }

        return $this->rules[] = new ExprBuilder($this->node);
    }
}
