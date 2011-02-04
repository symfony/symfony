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

    public function __construct($parent)
    {
        $this->parent = $parent;
    }

    public function ifTrue(\Closure $closure = null)
    {
        if (null === $closure) {
            $closure = function($v) { return true === $v; };
        }

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

    public function thenReplaceKeyWithAttribute($attribute)
    {
        $this->thenPart = function($v) {
            $newValue = array();
            foreach ($v as $k => $oldValue) {
                if (is_array($oldValue) && isset($oldValue['id'])) {
                    $k = $oldValue['id'];
                }

                $newValue[$k] = $oldValue;
            }

            return $newValue;
        };

        return $this;
    }

    public function thenEmptyArray()
    {
        $this->thenPart = function($v) { return array(); };

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