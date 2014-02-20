<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\NodeVisitor;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Node\Node;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractVisitor implements NodeVisitorInterface
{
    public function beforeTraversal(array $nodes, ExecutionContextInterface $context)
    {
    }

    public function afterTraversal(array $nodes, ExecutionContextInterface $context)
    {
    }

    public function visit(Node $node, ExecutionContextInterface $context)
    {
    }
}
