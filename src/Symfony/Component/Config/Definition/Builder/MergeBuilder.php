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
    public bool $allowFalse = false;
    public bool $allowOverwrite = true;

    public function __construct(
        protected NodeDefinition $node,
    ) {
    }

    /**
     * Sets whether the node can be unset.
     *
     * @return $this
     */
    public function allowUnset(bool $allow = true): static
    {
        $this->allowFalse = $allow;

        return $this;
    }

    /**
     * Sets whether the node can be overwritten.
     *
     * @return $this
     */
    public function denyOverwrite(bool $deny = true): static
    {
        $this->allowOverwrite = !$deny;

        return $this;
    }

    /**
     * Returns the related node.
     */
    public function end(): NodeDefinition|ArrayNodeDefinition|VariableNodeDefinition
    {
        return $this->node;
    }
}
