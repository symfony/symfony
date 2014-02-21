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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\Node;

/**
 * Checks class nodes whether their "Default" group is replaced by a group
 * sequence and adjusts the validation groups accordingly.
 *
 * If the "Default" group is replaced for a class node, and if the validated
 * groups of the node contain the group "Default", that group is replaced by
 * the group sequence specified in the class' metadata.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DefaultGroupReplacingVisitor extends AbstractVisitor
{
    /**
     * Replaces the "Default" group in the node's groups by the class' group
     * sequence.
     *
     * @param Node                      $node    The current node
     * @param ExecutionContextInterface $context The execution context
     */
    public function visit(Node $node, ExecutionContextInterface $context)
    {
        if (!$node instanceof ClassNode) {
            return;
        }

        if ($node->metadata->hasGroupSequence()) {
            // The group sequence is statically defined for the class
            $groupSequence = $node->metadata->getGroupSequence();
        } elseif ($node->metadata->isGroupSequenceProvider()) {
            // The group sequence is dynamically obtained from the validated
            // object
            /** @var \Symfony\Component\Validator\GroupSequenceProviderInterface $value */
            $groupSequence = $node->value->getGroupSequence();

            if (!$groupSequence instanceof GroupSequence) {
                $groupSequence = new GroupSequence($groupSequence);
            }
        } else {
            // The "Default" group is not overridden. Quit.
            return;
        }

        $key = array_search(Constraint::DEFAULT_GROUP, $node->groups);

        if (false !== $key) {
            // Replace the "Default" group by the group sequence
            $node->groups[$key] = $groupSequence;

            // Cascade the "Default" group when validating the sequence
            $groupSequence->cascadedGroup = Constraint::DEFAULT_GROUP;
        }
    }
}
