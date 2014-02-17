<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\NodeTraverser;

use Symfony\Component\Validator\Node\Node;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface NodeTraverserInterface
{
    public function addVisitor(NodeVisitorInterface $visitor);

    public function removeVisitor(NodeVisitorInterface $visitor);

    /**
     * @param Node[] $nodes
     */
    public function traverse(array $nodes);
}
