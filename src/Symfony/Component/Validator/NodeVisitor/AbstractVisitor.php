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
 * Base visitor with empty method stubs.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see NodeVisitorInterface
 */
abstract class AbstractVisitor implements NodeVisitorInterface
{
    /**
     * {@inheritdoc}
     */
    public function beforeTraversal($nodes, ExecutionContextInterface $context)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function afterTraversal($nodes, ExecutionContextInterface $context)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function visit(Node $node, ExecutionContextInterface $context)
    {
    }
}
