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
     * Adds a violation at the validation graph node with the given property
     * path.
     *
     * @param string       $propertyPath  The property path for the violation.
     * @param string       $message       The error message.
     * @param array        $params        The parameters parsed into the error message.
     * @param mixed        $invalidValue  The invalid, validated value.
     * @param integer|null $pluralization The number to use to pluralize of the message.
     * @param integer|null $code          The violation code.
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3.
     */
    public function addViolationAtPath($propertyPath, $message, array $params = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        trigger_error('addViolationAtPath() is deprecated since version 2.2 and will be removed in 2.3.', E_USER_DEPRECATED);

        $this->globalContext->getViolations()->add(new ConstraintViolation(
            null === $pluralization
                ? $this->translator->trans($message, $params, $this->translationDomain)
                : $this->translator->transChoice($message, $pluralization, $params, $this->translationDomain),
            $message,
            $params,
            $this->globalContext->getRoot(),
            $propertyPath,
            // check using func_num_args() to allow passing null values
            func_num_args() >= 4 ? $invalidValue : $this->value,
            $pluralization,
            $code
        ));
    }

    /**
     * Adds a violation at the validation graph node with the given property
     * path relative to the current property path.
     *
     * @param string       $subPath       The relative property path for the violation.
     * @param string       $message       The error message.
     * @param array        $params        The parameters parsed into the error message.
     * @param mixed        $invalidValue  The invalid, validated value.
     * @param integer|null $pluralization The number to use to pluralize of the message.
     * @param integer|null $code          The violation code.
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3. Use the
     *             method {@link addViolationAt} instead.
     */
    public function addViolationAtSubPath($subPath, $message, array $params = array(), $invalidValue = null, $pluralization = null, $code = null)
    {
        trigger_error('addViolationAtSubPath() is deprecated since version 2.2 and will be removed in 2.3. Use addViolationAt() instead.', E_USER_DEPRECATED);

        if (func_num_args() >= 4) {
            $this->addViolationAt($subPath, $message, $params, $invalidValue, $pluralization, $code);
        } else {
            // Needed in order to make the check for func_num_args() inside work
            $this->addViolationAt($subPath, $message, $params);
        }
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
            return $this->propertyPath . '.' . $subPath;
        }

        return $this->propertyPath . $subPath;
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
     * Returns the class name of the current node.
     *
     * @return string|null The class name or null, if the current node does not
     *                     hold information about a class.
     *
     * @see getClassName
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3. Use
     *             {@link getClassName} instead.
     */
    public function getCurrentClass()
    {
        trigger_error('getCurrentClass() is deprecated since version 2.2 and will be removed in 2.3. Use getClassName() instead', E_USER_DEPRECATED);

        return $this->getClassName();
    }

    /**
     * Returns the property name of the current node.
     *
     * @return string|null The property name or null, if the current node does
     *                     not hold information about a property.
     *
     * @see getPropertyName
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3. Use
     *             {@link getClassName} instead.
     */
    public function getCurrentProperty()
    {
        trigger_error('getCurrentProperty() is deprecated since version 2.2 and will be removed in 2.3. Use getClassName() instead', E_USER_DEPRECATED);

        return $this->getPropertyName();
    }

    /**
     * Returns the currently validated value.
     *
     * @return mixed The current value.
     *
     * @see getValue
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3. Use
     *             {@link getValue} instead.
     */
    public function getCurrentValue()
    {
        trigger_error('getCurrentValue() is deprecated since version 2.2 and will be removed in 2.3. Use getValue() instead', E_USER_DEPRECATED);

        return $this->value;
    }

    /**
     * Returns the graph walker instance.
     *
     * @return GraphWalker The graph walker.
     *
     * @deprecated Deprecated since version 2.2, to be removed in 2.3. Use
     *             {@link validate} and {@link validateValue} instead.
     */
    public function getGraphWalker()
    {
        trigger_error('getGraphWalker() is deprecated since version 2.2 and will be removed in 2.3. Use validate() and validateValue() instead', E_USER_DEPRECATED);

        return $this->globalContext->getVisitor()->getGraphWalker();
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
