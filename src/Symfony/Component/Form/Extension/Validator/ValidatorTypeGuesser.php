<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator;

use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;
use Symfony\Component\Validator\Constraint;

class ValidatorTypeGuesser implements FormTypeGuesserInterface
{
    private $metadataFactory;

    public function __construct(ClassMetadataFactoryInterface $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function guessType($class, $property)
    {
        $guesser = $this;

        return $this->guess($class, $property, function (Constraint $constraint) use ($guesser) {
            return $guesser->guessTypeForConstraint($constraint);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function guessRequired($class, $property)
    {
        $guesser = $this;

        return $this->guess($class, $property, function (Constraint $constraint) use ($guesser) {
            return $guesser->guessRequiredForConstraint($constraint);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function guessMaxLength($class, $property)
    {
        $guesser = $this;

        return $this->guess($class, $property, function (Constraint $constraint) use ($guesser) {
            return $guesser->guessMaxLengthForConstraint($constraint);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function guessMinLength($class, $property)
    {
        $guesser = $this;

        return $this->guess($class, $property, function (Constraint $constraint) use ($guesser) {
            return $guesser->guessMinLengthForConstraint($constraint);
        });
    }

    /**
     * Guesses a field class name for a given constraint
     *
     * @param  Constraint $constraint  The constraint to guess for
     * @return TypeGuess  The guessed field class and options
     */
    public function guessTypeForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\Type':
                switch ($constraint->type) {
                    case 'boolean':
                    case 'bool':
                        return new TypeGuess(
                            'checkbox',
                            array(),
                            Guess::MEDIUM_CONFIDENCE
                        );
                    case 'double':
                    case 'float':
                    case 'numeric':
                    case 'real':
                        return new TypeGuess(
                            'number',
                            array(),
                            Guess::MEDIUM_CONFIDENCE
                        );
                    case 'integer':
                    case 'int':
                    case 'long':
                        return new TypeGuess(
                            'integer',
                            array(),
                            Guess::MEDIUM_CONFIDENCE
                        );
                    case 'string':
                        return new TypeGuess(
                            'text',
                            array(),
                            Guess::LOW_CONFIDENCE
                        );
                    case '\DateTime':
                        return new TypeGuess(
                            'date',
                            array(),
                            Guess::MEDIUM_CONFIDENCE
                        );
                }
                break;
            case 'Symfony\Component\Validator\Constraints\Country':
                return new TypeGuess(
                    'country',
                    array(),
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Date':
                return new TypeGuess(
                    'date',
                    array('type' => 'string'),
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\DateTime':
                return new TypeGuess(
                    'datetime',
                    array('type' => 'string'),
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Email':
                return new TypeGuess(
                    'text',
                    array(),
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\File':
                return new TypeGuess(
                    'file',
                    array(),
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Image':
                return new TypeGuess(
                    'file',
                    array(),
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Ip':
                return new TypeGuess(
                    'text',
                    array(),
                    Guess::MEDIUM_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Language':
                return new TypeGuess(
                    'language',
                    array(),
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Locale':
                return new TypeGuess(
                    'locale',
                    array(),
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Max':
                return new TypeGuess(
                    'number',
                    array(),
                    Guess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\MaxLength':
                return new TypeGuess(
                    'text',
                    array(),
                    Guess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Min':
                return new TypeGuess(
                    'number',
                    array(),
                    Guess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\MinLength':
                return new TypeGuess(
                    'text',
                    array(),
                    Guess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Regex':
                return new TypeGuess(
                    'text',
                    array(),
                    Guess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Time':
                return new TypeGuess(
                    'time',
                    array('type' => 'string'),
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Url':
                return new TypeGuess(
                    'url',
                    array(),
                    Guess::HIGH_CONFIDENCE
                );
        }
    }

    /**
     * Guesses whether a field is required based on the given constraint
     *
     * @param  Constraint $constraint  The constraint to guess for
     * @return Guess       The guess whether the field is required
     */
    public function guessRequiredForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\NotNull':
                return new ValueGuess(
                    true,
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\NotBlank':
                return new ValueGuess(
                    true,
                    Guess::HIGH_CONFIDENCE
                );
            default:
                return new ValueGuess(
                    false,
                    Guess::LOW_CONFIDENCE
                );
        }
    }

    /**
     * Guesses a field's maximum length based on the given constraint
     *
     * @param  Constraint $constraint  The constraint to guess for
     * @return Guess       The guess for the maximum length
     */
    public function guessMaxLengthForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\MaxLength':
                return new ValueGuess(
                    $constraint->limit,
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Max':
                return new ValueGuess(
                    strlen((string)$constraint->limit),
                    Guess::HIGH_CONFIDENCE
                );
        }
    }

    /**
     * Guesses a field's minimum length based on the given constraint
     *
     * @param  Constraint $constraint  The constraint to guess for
     * @return Guess       The guess for the minimum length
     */
    public function guessMinLengthForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\MinLength':
                return new ValueGuess(
                    $constraint->limit,
                    Guess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Min':
                return new ValueGuess(
                    strlen((string)$constraint->limit),
                    Guess::HIGH_CONFIDENCE
                );
        }
    }

    /**
     * Iterates over the constraints of a property, executes a constraints on
     * them and returns the best guess
     *
     * @param string $class       The class to read the constraints from
     * @param string $property    The property for which to find constraints
     * @param \Closure $guessForConstraint   The closure that returns a guess
     *                            for a given constraint
     * @return Guess  The guessed value with the highest confidence
     */
    protected function guess($class, $property, \Closure $guessForConstraint)
    {
        $guesses = array();
        $classMetadata = $this->metadataFactory->getClassMetadata($class);

        if ($classMetadata->hasMemberMetadatas($property)) {
            $memberMetadatas = $classMetadata->getMemberMetadatas($property);

            foreach ($memberMetadatas as $memberMetadata) {
                $constraints = $memberMetadata->getConstraints();

                foreach ($constraints as $constraint) {
                    if ($guess = $guessForConstraint($constraint)) {
                        $guesses[] = $guess;
                    }
                }
            }
        }

        return Guess::getBestGuess($guesses);
    }
}
