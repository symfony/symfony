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

use Symfony\Component\Validator\Constraints\ExpressionValidator;

/**
 * Backwards compatible implementation of the {@link ConstraintValidatorFactoryInterface}.
 *
 * This class uses legacy constraint validators where possible to ensure
 * compatibility with the 2.4 validator API.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.5.3, to be removed in 3.0.
 */
class LegacyConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    protected $validators = array();

    private $propertyAccessor;

    public function __construct($propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstance(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case __NAMESPACE__.'\\Constraints\\All':
                $className = __NAMESPACE__.'\\Constraints\\LegacyAllValidator';
                break;
            case __NAMESPACE__.'\\Constraints\\Choice':
                $className = __NAMESPACE__.'\\Constraints\\LegacyChoiceValidator';
                break;
            case __NAMESPACE__.'\\Constraints\\Collection':
                $className = __NAMESPACE__.'\\Constraints\\LegacyCollectionValidator';
                break;
            case __NAMESPACE__.'\\Constraints\\Count':
                $className = __NAMESPACE__.'\\Constraints\\LegacyCountValidator';
                break;
            case __NAMESPACE__.'\\Constraints\\Length':
                $className = __NAMESPACE__.'\\Constraints\\LegacyLengthValidator';
                break;
            default:
                $className = $constraint->validatedBy();
                break;
        }

        if (!isset($this->validators[$className])) {
            $this->validators[$className] = 'validator.expression' === $className
                ? new ExpressionValidator($this->propertyAccessor)
                : new $className();
        }

        return $this->validators[$className];
    }
}
