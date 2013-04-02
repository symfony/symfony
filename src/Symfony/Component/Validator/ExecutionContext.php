<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Translation\TranslatorInterface;

/**
 * Default implementation of {@link ExecutionContextInterface}.
 *
 * This class is immutable by design.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ExecutionContext implements ExecutionContextInterface
{
    /**
     * @var GlobalExecutionContextInterface
     */
    private $globalContext;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var null|string
     */
    private $translationDomain;

    /**
     * @var MetadataInterface
     */
    private $metadata;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var string
     */
    private $group;

    /**
     * @var string
     */
    private $propertyPath;

    /**
     * Creates a new execution context.
     *
     * @param GlobalExecutionContextInterface $globalContext     The global context storing node-independent state.
     * @param TranslatorInterface             $translator        The translator for translating violation messages.
     * @param null|string                     $translationDomain The domain of the validation messages.
     * @param MetadataInterface               $metadata          The metadata of the validated node.
     * @param mixed                           $value             The value of the validated node.
     * @param string                          $group             The current validation group.
     * @param string                          $propertyPath      The property path to the current node.
     */
    public function __construct(GlobalExecutionContextInterface $globalContext, TranslatorInterface $translator, $translationDomain = null, MetadataInterface $metadata = null, $value = null, $group = null, $propertyPath = '')
    {
        if (null === $group) {
            $group = Constraint::DEFAULT_GROUP;
        }

        $this->globalContext = $globalContext;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->metadata = $metadata;
        $this->value = $value;
        $this->propertyPath = $propertyPath;
        $this->group = $group;
    }

    /**
     * {@inheritdoc}
     */
    public function addViolation($message, array $params = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        if (null === $pluralization) {
            $translatedMessage = $this->translator->trans($message, $params, $this->translationDomain);
        } else {
            try {
                $translatedMessage = $this->translator->transChoice($message, $pluralization, $params, $this->translationDomain);
            } catch (\InvalidArgumentException $e) {
                $translatedMessage = $this->translator->trans($message, $params, $this->translationDomain);
            }
        }

        $this->globalContext->getViolations()->add(new ConstraintViolation(
            $translatedMessage,
            $message,
            $params,
            $this->globalContext->getRoot(),
            $this->propertyPath,
            // check using func_num_args() to allow passing null values
            func_num_args() >= 3 ? $invalidValue : $this->value,
            $pluralization,
            $code
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function addViolationAt($subPath, $message, array $params = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        $this->globalContext->getViolations()->add(new ConstraintViolation(
            null === $pluralization
                ? $this->translator->trans($message, $params, $this->translationDomain)
                : $this->translator->transChoice($message, $pluralization, $params, $this->translationDomain),
            $message,
            $params,
            $this->globalContext->getRoot(),
            $this->getPropertyPath($subPath),
            // check using func_num_args() to allow passing null values
            func_num_args() >= 4 ? $invalidValue : $this->value,
            $pluralization,
            $code
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getViolations()
    {
        return $this->globalContext->getViolations();
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot()
    {
        return $this->globalContext->getRoot();
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyPath($subPath = '')
    {
        if ('' != $subPath && '' !== $this->propertyPath && '[' !== $subPath[0]) {
            return $this->propertyPath.'.'.$subPath;
        }

        return $this->propertyPath.$subPath;
    }

    /**
     * {@inheritdoc}
     */
    public function getClassName()
    {
        if ($this->metadata instanceof ClassBasedInterface) {
            return $this->metadata->getClassName();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyName()
    {
        if ($this->metadata instanceof PropertyMetadataInterface) {
            return $this->metadata->getPropertyName();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFor($value)
    {
        return $this->globalContext->getMetadataFactory()->getMetadataFor($value);
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, $subPath = '', $groups = null, $traverse = false, $deep = false)
    {
        $propertyPath = $this->getPropertyPath($subPath);

        foreach ($this->resolveGroups($groups) as $group) {
            $this->globalContext->getVisitor()->validate($value, $group, $propertyPath, $traverse, $deep);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateValue($value, $constraints, $subPath = '', $groups = null)
    {
        $constraints = is_array($constraints) ? $constraints : array($constraints);

        if (null === $groups && '' === $subPath) {
            $context = clone $this;
            $context->value = $value;
            $context->executeConstraintValidators($value, $constraints);

            return;
        }

        $propertyPath = $this->getPropertyPath($subPath);

        foreach ($this->resolveGroups($groups) as $group) {
            $context = clone $this;
            $context->value = $value;
            $context->group = $group;
            $context->propertyPath = $propertyPath;
            $context->executeConstraintValidators($value, $constraints);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFactory()
    {
        return $this->globalContext->getMetadataFactory();
    }

    /**
     * Executes the validators of the given constraints for the given value.
     *
     * @param mixed        $value       The value to validate.
     * @param Constraint[] $constraints The constraints to match against.
     */
    private function executeConstraintValidators($value, array $constraints)
    {
        foreach ($constraints as $constraint) {
            $validator = $this->globalContext->getValidatorFactory()->getInstance($constraint);
            $validator->initialize($this);
            $validator->validate($value, $constraint);
        }
    }

    /**
     * Returns an array of group names.
     *
     * @param null|string|string[] $groups The groups to resolve. If a single string is
     *                                     passed, it is converted to an array. If null
     *                                     is passed, an array containing the current
     *                                     group of the context is returned.
     *
     * @return array An array of validation groups.
     */
    private function resolveGroups($groups)
    {
        return $groups ? (array) $groups : (array) $this->group;
    }
}
