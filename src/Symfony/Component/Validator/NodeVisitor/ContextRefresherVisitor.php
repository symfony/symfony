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
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\Util\NodeStackInterface;

/**
 * Updates the current context with the current node of the validation
 * traversal.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ContextRefresherVisitor extends AbstractVisitor
{
    public function enterNode(Node $node, ExecutionContextInterface $context)
    {
        if (!$context instanceof NodeStackInterface) {
            throw new RuntimeException(sprintf(
                'The ContextRefresherVisitor only supports instances of class '.
                '"Symfony\Component\Validator\Context\NodeStackInterface". '.
                'An instance of class "%s" was given.',
                get_class($context)
            ));
        }

        $context->pushNode($node);
    }

    public function leaveNode(Node $node, ExecutionContextInterface $context)
    {
        if (!$context instanceof NodeStackInterface) {
            throw new RuntimeException(sprintf(
                'The ContextRefresherVisitor only supports instances of class '.
                '"Symfony\Component\Validator\Context\NodeStackInterface". '.
                'An instance of class "%s" was given.',
                get_class($context)
            ));
        }

        $context->popNode();
    }
}
