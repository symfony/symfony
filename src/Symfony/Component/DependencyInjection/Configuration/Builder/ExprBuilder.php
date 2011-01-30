<?php

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

class ExprBuilder
{
    public $parent;
    public $ifPart;
    public $thenPart;

    public function __construct($parent)
    {
        $this->parent = $parent;
    }

    public function ifTrue(\Closure $closure)
    {
        $this->ifPart = $closure;

        return $this;
    }

    public function ifString()
    {
        $this->ifPart = function($v) { return is_string($v); };

        return $this;
    }

    public function ifNull()
    {
        $this->ifPart = function($v) { return null === $v; };

        return $this;
    }

    public function ifArray()
    {
        $this->ifPart = function($v) { return is_array($v); };

        return $this;
    }

    public function then(\Closure $closure)
    {
        $this->thenPart = $closure;

        return $this;
    }

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