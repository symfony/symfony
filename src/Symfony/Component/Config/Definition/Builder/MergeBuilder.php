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
 * This class builds merge conditions.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class MergeBuilder
{
    protected $node;
    public $allowFalse;
    public $allowOverwrite;

    /**
     * Constructor
     *
     * @param NodeDefinition $node The related node
     */
    public function __construct(NodeDefinition $node)
    {
        $this->node = $node;
        $this->allowFalse = false;
        $this->allowOverwrite = true;
    }

    /**
     * Sets whether the node can be unset.
     *
     * @param Boolean $allow
     *
     * @return MergeBuilder
     */
    public function allowUnset($allow = true)
    {
        $this->allowFalse = $allow;

        return $this;
    }

    /**
     * Sets whether the node can be overwritten.
     *
     * @param Boolean $deny Whether the overwriting is forbidden or not
     *
     * @return MergeBuilder
     */
    public function denyOverwrite($deny = true)
    {
        $this->allowOverwrite = !$deny;

        return $this;
    }

    /**
     * Returns the related node.
     *
     * @return NodeDefinition
     */
    public function end()
    {
        return $this->node;
    }
}
