<?php

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

class NodeBuilder
{
    /************
     * READ-ONLY
     ************/
    public $name;
    public $type;
    public $key;
    public $parent;
    public $children;
    public $prototype;
    public $normalizeTransformations;
    public $beforeTransformations;
    public $afterTransformations;

    public function __construct($name, $type, $parent = null)
    {
        $this->name = $name;
        $this->type = $type;
        $this->parent = $parent;

        $this->children =
        $this->beforeTransformations =
        $this->afterTransformations =
        $this->normalizeTransformations = array();
    }

    /****************************
     * FLUID INTERFACE
     ****************************/

    public function node($name, $type)
    {
        $node = new NodeBuilder($name, $type, $this);

        return $this->children[$name] = $node;
    }

    public function normalize($key, $plural = null)
    {
        if (null === $plural) {
            $plural = $key.'s';
        }

        $this->normalizeTransformations[] = array($key, $plural);

        return $this;
    }

    public function key($name)
    {
        $this->key = $name;

        return $this;
    }

    public function before(\Closure $closure = null)
    {
        if (null !== $closure) {
            $this->beforeTransformations[] = $closure;

            return $this;
        }

        return $this->beforeTransformations[] = new ExprBuilder($this);
    }

    public function prototype($type)
    {
        return $this->prototype = new NodeBuilder(null, $type, $this);
    }

    public function after(\Closure $closure = null)
    {
        if (null !== $closure) {
            $this->afterTransformations[] = $closure;

            return $this;
        }

        return $this->afterTransformations[] = new ExprBuilder($this);
    }

    public function end()
    {
        return $this->parent;
    }
}