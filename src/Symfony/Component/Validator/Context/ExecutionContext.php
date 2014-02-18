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

use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\ClassBasedInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Group\GroupManagerInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\Util\PropertyPath;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;

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

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string
     */
    private $translationDomain;

    public function __construct($root, ValidatorInterface $validator, GroupManagerInterface $groupManager, TranslatorInterface $translator, $translationDomain = null)
    {
        $this->root = $root;
        $this->validator = $validator;
        $this->groupManager = $groupManager;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->violations = new ConstraintViolationList();
        $this->nodeStack = new \SplStack();
    }

    public function pushNode(Node $node)
    {
        if (null !== $this->node) {
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

    public function addViolation($message, array $parameters = array())
    {
        $this->violations->add(new ConstraintViolation(
            $this->translator->trans($message, $parameters, $this->translationDomain),
            $message,
            $parameters,
            $this->root,
            $this->getPropertyPath(),
            $this->getValue(),
            null,
            null
        ));
    }

    public function buildViolation($message, array $parameters = array())
    {
        return new ConstraintViolationBuilder(
            $this->violations,
            $message,
            $parameters,
            $this->root,
            $this->getPropertyPath(),
            $this->getValue(),
            $this->translator,
            $this->translationDomain
        );
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

        return PropertyPath::append($propertyPath, $subPath);
    }

    /**
     * @return ValidatorInterface
     */
    public function getValidator()
    {
        return $this->validator;
    }
}
