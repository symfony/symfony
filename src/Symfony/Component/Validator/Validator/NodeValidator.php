<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Validator;

use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Context\ExecutionContextManagerInterface;
use Symfony\Component\Validator\Group\GroupManagerInterface;
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\NodeTraverser\AbstractVisitor;
use Symfony\Component\Validator\NodeTraverser\NodeTraverserInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NodeValidator extends AbstractVisitor implements GroupManagerInterface
{
    private $validatedObjects = array();

    /**
     * @var ConstraintValidatorFactoryInterface
     */
    private $validatorFactory;

    /**
     * @var ExecutionContextManagerInterface
     */
    private $contextManager;

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

    public function initialize(ExecutionContextManagerInterface $contextManager)
    {
        $this->contextManager = $contextManager;
    }

    public function afterTraversal(array $nodes)
    {
        $this->validatedObjects = array();
    }

    public function enterNode(Node $node)
    {
        $objectHash = $node instanceof ClassNode
            ? spl_object_hash($node->value)
            : null;

        // if group (=[<G1,G2>,G3,G4]) contains group sequence (=<G1,G2>)
        // then call traverse() with each entry of the group sequence and abort
        // if necessary (G1, G2)
        // finally call traverse() with remaining entries ([G3,G4]) or
        // simply continue traversal (if possible)

        foreach ($node->groups as $group) {
            // Validate object nodes only once per group
            if (null !== $objectHash) {
                // Use the object hash for group sequences
                $groupHash = is_object($group) ? spl_object_hash($group) : $group;

                // Exit, if the object is already validated for the current group
                if (isset($this->validatedObjects[$objectHash][$groupHash])) {
                    return false;
                }

                // Remember validating this object before starting and possibly
                // traversing the object graph
                $this->validatedObjects[$objectHash][$groupHash] = true;
            }

            // Validate group sequence until a violation is generated
            if (!$group instanceof GroupSequence) {
                $this->validateNodeForGroup($node, $group);

                continue;
            }

            // Only traverse group sequences at class, not at property level
            if (!$node instanceof ClassNode) {
                continue;
            }

            $this->traverseGroupSequence($node, $group);

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

    private function traverseGroupSequence(ClassNode $node, GroupSequence $groupSequence)
    {
        $context = $this->contextManager->getCurrentContext();
        $violationCount = count($context->getViolations());

        foreach ($groupSequence->groups as $groupInSequence) {
            $this->nodeTraverser->traverse(array(new ClassNode(
                $node->value,
                $node->metadata,
                $node->propertyPath,
                array($groupInSequence),
                array($groupSequence->cascadedGroup ?: $groupInSequence)
            )));

            // Abort sequence validation if a violation was generated
            if (count($context->getViolations()) > $violationCount) {
                break;
            }
        }
    }

    /**
     * @param Node $node
     * @param      $group
     *
     * @throws \Exception
     */
    private function validateNodeForGroup(Node $node, $group)
    {
        try {
            $this->currentGroup = $group;

            foreach ($node->metadata->findConstraints($group) as $constraint) {
                $validator = $this->validatorFactory->getInstance($constraint);
                $validator->initialize($this->contextManager->getCurrentContext());
                $validator->validate($node->value, $constraint);
            }

            $this->currentGroup = null;
        } catch (\Exception $e) {
            $this->currentGroup = null;

            throw $e;
        }
    }
}
