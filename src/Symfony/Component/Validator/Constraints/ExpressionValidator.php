<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@symfony.com>
 */
class ExpressionValidator extends ConstraintValidator
{
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var ExpressionLanguage
     */
    private $expressionLanguage;

    /**
     * @param PropertyAccessorInterface|null $propertyAccessor Optional as of Symfony 2.5
     *
     * @throws UnexpectedTypeException If the property accessor is invalid
     */
    public function __construct($propertyAccessor = null)
    {
        if (null !== $propertyAccessor && !$propertyAccessor instanceof PropertyAccessorInterface) {
            throw new UnexpectedTypeException($propertyAccessor, 'null or \Symfony\Component\PropertyAccess\PropertyAccessorInterface');
        }

        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Expression) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Expression');
        }

        $variables = array();

        // Symfony 2.5+
        if ($this->context instanceof ExecutionContextInterface) {
            $variables['value'] = $value;
            $variables['this'] = $this->context->getObject();
        } elseif (null === $this->context->getPropertyName()) {
            $variables['value'] = $value;
            $variables['this'] = $value;
        } else {
            $root = $this->context->getRoot();
            $variables['value'] = $value;

            if (is_object($root)) {
                // Extract the object that the property belongs to from the object
                // graph
                $path = new PropertyPath($this->context->getPropertyPath());
                $parentPath = $path->getParent();
                $variables['this'] = $parentPath ? $this->getPropertyAccessor()->getValue($root, $parentPath) : $root;
            } else {
                $variables['this'] = null;
            }
        }

        if (!$this->getExpressionLanguage()->evaluate($constraint->expression, $variables)) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->addViolation();
            }
        }
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new ExpressionLanguage();
        }

        return $this->expressionLanguage;
    }

    private function getPropertyAccessor()
    {
        if (null === $this->propertyAccessor) {
            if (!class_exists('Symfony\Component\PropertyAccess\PropertyAccess')) {
                throw new RuntimeException('Unable to use expressions as the Symfony PropertyAccess component is not installed.');
            }
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor;
    }
}
