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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\ExpressionValidator;
use Symfony\Component\Validator\Constraints\L18nValidator;

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

    private $propertyAccessor;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct($propertyAccessor = null, $requestStack = null)
    {
        $this->propertyAccessor = $propertyAccessor;
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstance(Constraint $constraint)
    {
        $className = $constraint->validatedBy();

        if (!isset($this->validators[$className])) {
            switch ($className) {
                case 'validator.expression':
                    $this->validators[$className] = new ExpressionValidator($this->propertyAccessor);
                    break;
                case 'validator.l18n':
                    $this->validators[$className] = new L18nValidator($this->requestStack);
                    break;
                default:
                    $this->validators[$className] = new $className();
                    break;
            }
        }

        return $this->validators[$className];
    }
}
