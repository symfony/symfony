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
 * Validates a node's value against the constraints defined in it's metadata.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NodeValidationVisitor extends AbstractVisitor implements GroupManagerInterface
{
    /**
     * @var ConstraintValidatorFactoryInterface
     */
    private $validatorFactory;

    /**
     * @var NodeTraverserInterface
     */
    private $nodeTraverser;

    /**
     * The currently validated group.
     *
     * @var string
     */
    private $currentGroup;

    /**
     * Creates a new visitor.
     *
     * @param NodeTraverserInterface              $nodeTraverser    The node traverser
     * @param ConstraintValidatorFactoryInterface $validatorFactory The validator factory
     */
    public function __construct(NodeTraverserInterface $nodeTraverser, ConstraintValidatorFactoryInterface $validatorFactory)
    {
        $this->validatorFactory = $validatorFactory;
        $this->nodeTraverser = $nodeTraverser;
    }

    /**
     * Validates a node's value against the constraints defined in the node's
     * metadata.
     *
     * Objects and constraints that were validated before in the same context
     * will be skipped.
     *
     * @param Node                      $node    The current node
     * @param ExecutionContextInterface $context The execution context
     *
     * @return Boolean Whether to traverse the successor nodes
     */
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
            // Even if we remove the following clause, the constraints on an
            // object won't be validated again due to the measures taken in
            // validateNodeForGroup().
            // The following shortcut, however, prevents validatedNodeForGroup()
            // from being called at all and enhances performance a bit.
            if ($node instanceof ClassNode) {
                // Use the object hash for group sequences
                $groupHash = is_object($group) ? spl_object_hash($group) : $group;

                if ($context->isObjectValidatedForGroup($objectHash, $groupHash)) {
                    // Skip this group when validating the successor nodes
                    // (property and/or collection nodes)
                    unset($node->groups[$key]);

                    continue;
                }

                $context->markObjectAsValidatedForGroup($objectHash, $groupHash);
            }

            // Validate normal group
            if (!$group instanceof GroupSequence) {
                $this->validateNodeForGroup($node, $group, $context, $objectHash);

                continue;
            }

            // Traverse group sequence until a violation is generated
            $this->traverseGroupSequence($node, $group, $context);

            // Skip the group sequence when validating successor nodes
            unset($node->groups[$key]);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentGroup()
    {
        return $this->currentGroup;
    }

    /**
     * Validates a node's value in each group of a group sequence.
     *
     * If any of the groups' constraints generates a violation, subsequent
     * groups are not validated anymore.
     *
     * @param Node                      $node          The validated node
     * @param GroupSequence             $groupSequence The group sequence
     * @param ExecutionContextInterface $context       The execution context
     */
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

    /**
     * Validates a node's value against all constraints in the given group.
     *
     * @param Node                      $node       The validated node
     * @param string                    $group      The group to validate
     * @param ExecutionContextInterface $context    The execution context
     * @param string                    $objectHash The hash of the node's
     *                                              object (if any)
     *
     * @throws \Exception
     */
    private function validateNodeForGroup(Node $node, $group, ExecutionContextInterface $context, $objectHash)
    {
        try {
            $this->currentGroup = $group;

            foreach ($node->metadata->findConstraints($group) as $constraint) {
                // Prevent duplicate validation of constraints, in the case
                // that constraints belong to multiple validated groups
                if (null !== $objectHash) {
                    $constraintHash = spl_object_hash($constraint);

                    if ($node instanceof ClassNode) {
                        if ($context->isClassConstraintValidated($objectHash, $constraintHash)) {
                            continue;
                        }

                        $context->markClassConstraintAsValidated($objectHash, $constraintHash);
                    } elseif ($node instanceof PropertyNode) {
                        $propertyName = $node->metadata->getPropertyName();

                        if ($context->isPropertyConstraintValidated($objectHash, $propertyName, $constraintHash)) {
                            continue;
                        }

                        $context->markPropertyConstraintAsValidated($objectHash, $propertyName, $constraintHash);
                    }
                }

                $validator = $this->validatorFactory->getInstance($constraint);
                $validator->initialize($context);
                $validator->validate($node->value, $constraint);
            }

            $this->currentGroup = null;
        } catch (\Exception $e) {
            // Should be put into a finally block once we switch to PHP 5.5
            $this->currentGroup = null;

            throw $e;
        }
    }
}
