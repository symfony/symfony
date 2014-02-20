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

use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Group\GroupManagerInterface;
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\CollectionNode;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\Node\PropertyNode;
use Symfony\Component\Validator\NodeTraverser\NodeTraverserInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NodeValidatorVisitor extends AbstractVisitor implements GroupManagerInterface
{
    private $validatedObjects = array();

    private $validatedConstraints = array();

    /**
     * @var ConstraintValidatorFactoryInterface
     */
    private $validatorFactory;

    /**
     * @var NodeTraverserInterface
     */
    private $nodeTraverser;

    private $currentGroup;

    public function __construct(NodeTraverserInterface $nodeTraverser, ConstraintValidatorFactoryInterface $validatorFactory)
    {
        $this->validatorFactory = $validatorFactory;
        $this->nodeTraverser = $nodeTraverser;
    }

    public function afterTraversal(array $nodes, ExecutionContextInterface $context)
    {
        $this->validatedObjects = array();
        $this->validatedConstraints = array();
    }

    public function visit(Node $node, ExecutionContextInterface $context)
    {
        if ($node instanceof CollectionNode) {
            return true;
        }

        if ($node instanceof ClassNode) {
            $objectHash = spl_object_hash($node->value);
        } elseif ($node instanceof PropertyNode) {
            $objectHash = spl_object_hash($node->object);
        } else {
            $objectHash = null;
        }

        // if group (=[<G1,G2>,G3,G4]) contains group sequence (=<G1,G2>)
        // then call traverse() with each entry of the group sequence and abort
        // if necessary (G1, G2)
        // finally call traverse() with remaining entries ([G3,G4]) or
        // simply continue traversal (if possible)

        foreach ($node->groups as $key => $group) {
            // Remember which object was validated for which group
            // Skip validation if the object was already validated for this
            // group
            if ($node instanceof ClassNode) {
                // Use the object hash for group sequences
                $groupHash = is_object($group) ? spl_object_hash($group) : $group;

                if (isset($this->validatedObjects[$objectHash][$groupHash])) {
                    // Skip this group when validating properties
                    unset($node->groups[$key]);

                    continue;
                }

                $this->validatedObjects[$objectHash][$groupHash] = true;
            }

            // Validate normal group
            if (!$group instanceof GroupSequence) {
                $this->validateNodeForGroup($objectHash, $node, $group, $context);

                continue;
            }

            // Skip the group sequence when validating properties
            unset($node->groups[$key]);

            // Traverse group sequence until a violation is generated
            $this->traverseGroupSequence($node, $group, $context);

            // Optimization: If the groups only contain the group sequence,
            // we can skip the traversal for the properties of the object
            if (1 === count($node->groups)) {
                return false;
            }
        }

        return true;
    }

    public function getCurrentGroup()
    {
        return $this->currentGroup;
    }

    private function traverseGroupSequence(Node $node, GroupSequence $groupSequence, ExecutionContextInterface $context)
    {
        $violationCount = count($context->getViolations());

        foreach ($groupSequence->groups as $groupInSequence) {
            $node = clone $node;
            $node->groups = array($groupInSequence);

            if (null !== $groupSequence->cascadedGroup) {
                $node->cascadedGroups = array($groupSequence->cascadedGroup);
            }

            $this->nodeTraverser->traverse(array($node), $context);

            // Abort sequence validation if a violation was generated
            if (count($context->getViolations()) > $violationCount) {
                break;
            }
        }
    }

    private function validateNodeForGroup($objectHash, Node $node, $group, ExecutionContextInterface $context)
    {
        try {
            $this->currentGroup = $group;

            foreach ($node->metadata->findConstraints($group) as $constraint) {
                // Remember the validated constraints of each object to prevent
                // duplicate validation of constraints that belong to multiple
                // validated groups
                if (null !== $objectHash) {
                    $constraintHash = spl_object_hash($constraint);

                    if (isset($this->validatedConstraints[$objectHash][$constraintHash])) {
                        continue;
                    }

                    $this->validatedConstraints[$objectHash][$constraintHash] = true;
                }

                $validator = $this->validatorFactory->getInstance($constraint);
                $validator->initialize($context);
                $validator->validate($node->value, $constraint);
            }

            $this->currentGroup = null;
        } catch (\Exception $e) {
            $this->currentGroup = null;

            throw $e;
        }
    }
}
