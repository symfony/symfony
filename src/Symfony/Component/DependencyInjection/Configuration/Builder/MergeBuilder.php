<?php

namespace Symfony\Component\DependencyInjection\Configuration\Builder;

/**
 * This class builds merge conditions.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class MergeBuilder
{
    public $parent;
    public $allowFalse;
    public $allowOverwrite;

    public function __construct($parent)
    {
        $this->parent = $parent;
        $this->allowFalse = false;
        $this->allowOverwrite = true;
    }

    public function allowUnset($allow = true)
    {
        $this->allowFalse = $allow;

        return $this;
    }

    public function denyOverwrite($deny = true)
    {
        $this->allowOverwrite = !$deny;

        return $this;
    }

    public function end()
    {
        return $this->parent;
    }
}