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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraints\ExpressionValidator;

/**
 * Default implementation of the ConstraintValidatorFactoryInterface.
 *
 * This enforces the convention that the validatedBy() method on any
 * Constraint will return the class name of the ConstraintValidator that
 * should validate the Constraint.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    protected $validators = array();

    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritDoc}
     */
    public function getInstance(Constraint $constraint)
    {
        $className = $constraint->validatedBy();

        // The second condition is a hack that is needed when CollectionValidator
        // calls itself recursively (Collection constraints can be nested).
        // Since the context of the validator is overwritten when initialize()
        // is called for the nested constraint, the outer validator is
        // acting on the wrong context when the nested validation terminates.
        //
        // A better solution - which should be approached in Symfony 3.0 - is to
        // remove the initialize() method and pass the context as last argument
        // to validate() instead.
        if (!isset($this->validators[$className]) || 'Symfony\Component\Validator\Constraints\CollectionValidator' === $className) {
            $this->validators[$className] = 'validator.expression' === $className
                ? new ExpressionValidator($this->propertyAccessor)
                : new $className();
        }

        return $this->validators[$className];
    }
}
