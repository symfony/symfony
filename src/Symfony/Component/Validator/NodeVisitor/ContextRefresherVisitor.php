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

/**
 * Updates the current context with the current node of the validation
 * traversal.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ContextRefresherVisitor extends AbstractVisitor
{
    public function visit(Node $node, ExecutionContextInterface $context)
    {
        if (!$context instanceof NodeObserverInterface) {
            throw new RuntimeException(sprintf(
                'The ContextRefresherVisitor only supports instances of class '.
                '"Symfony\Component\Validator\NodeVisitor\NodeObserverInterface". '.
                'An instance of class "%s" was given.',
                get_class($context)
            ));
        }

        $context->setCurrentNode($node);
    }
}
