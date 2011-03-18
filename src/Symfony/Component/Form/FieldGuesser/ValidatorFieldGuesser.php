<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\FieldGuesser;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;

/**
 * Guesses form fields from the metadata of the a Validator class
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ValidatorFieldGuesser implements FieldGuesserInterface
{
    /**
     * Constructor
     *
     * @param ClassMetadataFactoryInterface $metadataFactory
     */
    public function __construct(ClassMetadataFactoryInterface $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * @inheritDoc
     */
    public function guessType($class, $property)
    {
        $guesser = $this;

        return $this->guess($class, $property, function (Constraint $constraint) use ($guesser) {
            return $guesser->guessTypeForConstraint($constraint);
        });
    }

    /**
     * @inheritDoc
     */
    public function guessRequired($class, $property)
    {
        $guesser = $this;

        return $this->guess($class, $property, function (Constraint $constraint) use ($guesser) {
            return $guesser->guessRequiredForConstraint($constraint);
        });
    }

    /**
     * @inheritDoc
     */
    public function guessMaxLength($class, $property)
    {
        $guesser = $this;

        return $this->guess($class, $property, function (Constraint $constraint) use ($guesser) {
            return $guesser->guessMaxLengthForConstraint($constraint);
        });
    }

    /**
     * Iterates over the constraints of a property, executes a constraints on
     * them and returns the best guess
     *
     * @param string $class       The class to read the constraints from
     * @param string $property    The property for which to find constraints
     * @param \Closure $guessForConstraint   The closure that returns a guess
     *                            for a given constraint
     * @return FieldGuess  The guessed value with the highest confidence
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

        return FieldGuess::getBestGuess($guesses);
    }

    /**
     * Guesses a field class name for a given constraint
     *
     * @param  Constraint $constraint  The constraint to guess for
     * @return FieldTypeGuess  The guessed field class and options
     */
    public function guessTypeForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\AssertType':
                switch ($constraint->type) {
                    case 'boolean':
                    case 'bool':
                        return new FieldTypeGuess(
                            'checkbox',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                    case 'double':
                    case 'float':
                    case 'numeric':
                    case 'real':
                        return new FieldTypeGuess(
                            'number',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                    case 'integer':
                    case 'int':
                    case 'long':
                        return new FieldTypeGuess(
                            'integer',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                    case 'string':
                        return new FieldTypeGuess(
                            'text',
                            array(),
                            FieldGuess::LOW_CONFIDENCE
                        );
                    case '\DateTime':
                        return new FieldTypeGuess(
                            'date',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                }
                break;
            case 'Symfony\Component\Validator\Constraints\Choice':
                return new FieldTypeGuess(
                    'choice',
                    array('choices' => $constraint->choices),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Country':
                return new FieldTypeGuess(
                    'country',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Date':
                return new FieldTypeGuess(
                    'date',
                    array('type' => 'string'),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\DateTime':
                return new FieldTypeGuess(
                    'datetime',
                    array('type' => 'string'),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Email':
                return new FieldTypeGuess(
                    'text',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\File':
                return new FieldTypeGuess(
                    'file',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Image':
                return new FieldTypeGuess(
                    'file',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Ip':
                return new FieldTypeGuess(
                    'text',
                    array(),
                    FieldGuess::MEDIUM_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Language':
                return new FieldTypeGuess(
                    'language',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Locale':
                return new FieldTypeGuess(
                    'locale',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Max':
                return new FieldTypeGuess(
                    'number',
                    array(),
                    FieldGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\MaxLength':
                return new FieldTypeGuess(
                    'text',
                    array(),
                    FieldGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Min':
                return new FieldTypeGuess(
                    'number',
                    array(),
                    FieldGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\MinLength':
                return new FieldTypeGuess(
                    'text',
                    array(),
                    FieldGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Regex':
                return new FieldTypeGuess(
                    'text',
                    array(),
                    FieldGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Time':
                return new FieldTypeGuess(
                    'time',
                    array('type' => 'string'),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Url':
                return new FieldTypeGuess(
                    'url',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
        }
    }

    /**
     * Guesses whether a field is required based on the given constraint
     *
     * @param  Constraint $constraint  The constraint to guess for
     * @return FieldGuess       The guess whether the field is required
     */
    public function guessRequiredForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\NotNull':
                return new FieldGuess(
                    true,
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\NotBlank':
                return new FieldGuess(
                    true,
                    FieldGuess::HIGH_CONFIDENCE
                );
            default:
                return new FieldGuess(
                    false,
                    FieldGuess::LOW_CONFIDENCE
                );
        }
    }

    /**
     * Guesses a field's maximum length based on the given constraint
     *
     * @param  Constraint $constraint  The constraint to guess for
     * @return FieldGuess       The guess for the maximum length
     */
    public function guessMaxLengthForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\MaxLength':
                return new FieldGuess(
                    $constraint->limit,
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Max':
                return new FieldGuess(
                    strlen((string)$constraint->limit),
                    FieldGuess::HIGH_CONFIDENCE
                );
        }
    }
}