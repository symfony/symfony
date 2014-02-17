<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Context;

use Symfony\Component\Validator\ClassBasedInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Group\GroupManagerInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ExecutionContext implements ExecutionContextInterface
{
    private $root;

    private $violations;

    /**
     * @var Node
     */
    private $node;

    /**
     * @var \SplStack
     */
    private $nodeStack;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var GroupManagerInterface
     */
    private $groupManager;

    public function __construct(ValidatorInterface $validator, GroupManagerInterface $groupManager)
    {
        $this->validator = $validator;
        $this->groupManager = $groupManager;
        $this->violations = new ConstraintViolationList();
    }

    public function pushNode(Node $node)
    {
        if (null === $this->node) {
            $this->root = $node->value;
        } else {
            $this->nodeStack->push($this->node);
        }

        $this->node = $node;
    }

    public function popNode()
    {
        $poppedNode = $this->node;

        if (0 === count($this->nodeStack)) {
            $this->node = null;

            return $poppedNode;
        }

        if (1 === count($this->nodeStack)) {
            $this->nodeStack->pop();
            $this->node = null;

            return $poppedNode;
        }

        $this->nodeStack->pop();
        $this->node = $this->nodeStack->top();

        return $poppedNode;
    }

    public function addViolation($message, array $params = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
    }

    public function buildViolation($message)
    {

    }

    public function getViolations()
    {
        return $this->violations;
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function getValue()
    {
        return $this->node ? $this->node->value : null;
    }

    public function getMetadata()
    {
        return $this->node ? $this->node->metadata : null;
    }

    public function getGroup()
    {
        return $this->groupManager->getCurrentGroup();
    }

    public function getClassName()
    {
        $metadata = $this->getMetadata();

        return $metadata instanceof ClassBasedInterface ? $metadata->getClassName() : null;
    }

    public function getPropertyName()
    {
        $metadata = $this->getMetadata();

        return $metadata instanceof PropertyMetadataInterface ? $metadata->getPropertyName() : null;
    }

    public function getPropertyPath($subPath = '')
    {
        $propertyPath = $this->node ? $this->node->propertyPath : '';

        if (strlen($subPath) > 0) {
            if ('[' === $subPath{1}) {
                return $propertyPath.$subPath;
            }

            return $propertyPath ? $propertyPath.'.'.$subPath : $subPath;
        }

        return $propertyPath;
    }

    /**
     * @return ValidatorInterface
     */
    public function getValidator()
    {
        return $this->validator;
    }
}
