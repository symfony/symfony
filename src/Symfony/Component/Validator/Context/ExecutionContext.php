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
use Symfony\Component\Validator\Exception\BadMethodCallException;
use Symfony\Component\Validator\ExecutionContextInterface as LegacyExecutionContextInterface;
use Symfony\Component\Validator\Group\GroupManagerInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\NodeVisitor\NodeObserverInterface;
use Symfony\Component\Validator\Util\PropertyPath;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;

/**
 * The context used and created by {@link ExecutionContextFactory}.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see ExecutionContextInterface
 */
class ExecutionContext implements ExecutionContextInterface, LegacyExecutionContextInterface, NodeObserverInterface
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * The root value of the validated object graph.
     *
     * @var mixed
     */
    private $root;

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

    /**
     * The violations generated in the current context.
     *
     * @var ConstraintViolationList
     */
    private $violations;

    /**
     * The current node under validation.
     *
     * @var Node
     */
    private $node;

    /**
     * Creates a new execution context.
     *
     * @param ValidatorInterface    $validator         The validator
     * @param mixed                 $root              The root value of the
     *                                                 validated object graph
     * @param GroupManagerInterface $groupManager      The manager for accessing
     *                                                 the currently validated
     *                                                 group
     * @param TranslatorInterface   $translator        The translator
     * @param string|null           $translationDomain The translation domain to
     *                                                 use for translating
     *                                                 violation messages
     *
     * @internal Called by {@link ExecutionContextFactory}. Should not be used
     *           in user code.
     */
    public function __construct(ValidatorInterface $validator, $root, GroupManagerInterface $groupManager, TranslatorInterface $translator, $translationDomain = null)
    {
        $this->validator = $validator;
        $this->root = $root;
        $this->groupManager = $groupManager;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->violations = new ConstraintViolationList();
    }

    /**
     * Sets the values of the context to match the given node.
     *
     * @param Node $node The currently validated node
     */
    public function setCurrentNode(Node $node)
    {
        $this->node = $node;
    }

    /**
     * {@inheritdoc}
     */
    public function addViolation($message, array $parameters = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        // The parameters $invalidValue and following are ignored by the new
        // API, as they are not present in the new interface anymore.
        // You should use buildViolation() instead.

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

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function getViolations()
    {
        return $this->violations;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        return $this->node ? $this->node->value : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        return $this->node ? $this->node->metadata : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroup()
    {
        return $this->groupManager->getCurrentGroup();
    }

    /**
     * {@inheritdoc}
     */
    public function getClassName()
    {
        $metadata = $this->getMetadata();

        return $metadata instanceof ClassBasedInterface ? $metadata->getClassName() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyName()
    {
        $metadata = $this->getMetadata();

        return $metadata instanceof PropertyMetadataInterface ? $metadata->getPropertyName() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyPath($subPath = '')
    {
        $propertyPath = $this->node ? $this->node->propertyPath : '';

        return PropertyPath::append($propertyPath, $subPath);
    }

    /**
     * {@inheritdoc}
     */
    public function addViolationAt($subPath, $message, array $parameters = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        throw new BadMethodCallException(
            'addViolationAt() is not supported anymore in the new API. '.
            'Please use buildViolation() or enable the legacy mode.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, $subPath = '', $groups = null, $traverse = false, $deep = false)
    {
        throw new BadMethodCallException(
            'validate() is not supported anymore in the new API. '.
            'Please use getValidator() or enable the legacy mode.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function validateValue($value, $constraints, $subPath = '', $groups = null)
    {
        throw new BadMethodCallException(
            'validateValue() is not supported anymore in the new API. '.
            'Please use getValidator() or enable the legacy mode.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFactory()
    {
        throw new BadMethodCallException(
            'getMetadataFactory() is not supported anymore in the new API. '.
            'Please use getMetadataFor() or hasMetadataFor() or enable the '.
            'legacy mode.'
        );
    }
}
