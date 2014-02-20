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
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GroupSequenceResolverVisitor extends AbstractVisitor
{
    public function visit(Node $node, ExecutionContextInterface $context)
    {
        if (!$node instanceof ClassNode) {
            return;
        }

        if ($node->metadata->hasGroupSequence()) {
            $groupSequence = $node->metadata->getGroupSequence();
        } elseif ($node->metadata->isGroupSequenceProvider()) {
            /** @var \Symfony\Component\Validator\GroupSequenceProviderInterface $value */
            $groupSequence = $node->value->getGroupSequence();

            // TODO test
            if (!$groupSequence instanceof GroupSequence) {
                $groupSequence = new GroupSequence($groupSequence);
            }
        } else {
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
