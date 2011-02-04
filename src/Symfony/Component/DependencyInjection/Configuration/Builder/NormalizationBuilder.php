<?php

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

/**
 * This class builds normalization conditions.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class NormalizationBuilder
{
    public $parent;
    public $before;
    public $remappings;

    public function __construct($parent)
    {
        $this->parent = $parent;

        $this->keys = false;

        $this->remappings =
        $this->before =
        $this->after = array();
    }

    public function remap($key, $plural = null)
    {
        if (null === $plural) {
            $plural = $key.'s';
        }

        $this->remappings[] = array($key, $plural);

        return $this;
    }

    public function before(\Closure $closure = null)
    {
        if (null !== $closure) {
            $this->before[] = $closure;

            return $this;
        }

        return $this->before[] = new ExprBuilder($this->parent);
    }
}