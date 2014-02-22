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
 * Informs the execution context about the currently validated node.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ContextUpdateVisitor extends AbstractVisitor
{
    /**
     * Updates the execution context.
     *
     * @param Node                      $node    The current node
     * @param ExecutionContextInterface $context The execution context
     */
    public function visit(Node $node, ExecutionContextInterface $context)
    {
        $context->setValue($node->value);
        $context->setMetadata($node->metadata);
        $context->setPropertyPath($node->propertyPath);
    }
}
