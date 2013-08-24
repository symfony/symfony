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
 * An interface that must be implemented by nodes which can have children
 *
 * @author Victor Berchet <victor@suumit.com>
 */
interface ParentNodeDefinitionInterface
{
    /**
     * @since v2.1.0
     */
    public function children();

    /**
     * @since v2.1.0
     */
    public function append(NodeDefinition $node);

    /**
     * @since v2.1.0
     */
    public function setBuilder(NodeBuilder $builder);
}
