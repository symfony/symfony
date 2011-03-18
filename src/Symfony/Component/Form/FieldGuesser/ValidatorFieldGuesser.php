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
    public function guessIdentifier($class, $property)
    {
        $guesser = $this;

        return $this->guess($class, $property, function (Constraint $constraint) use ($guesser) {
            return $guesser->guessIdentifierForConstraint($constraint);
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
     * @return FieldIdentifierGuess  The guessed field class and options
     */
    public function guessIdentifierForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\AssertType':
                switch ($constraint->type) {
                    case 'boolean':
                    case 'bool':
                        return new FieldIdentifierGuess(
                            'checkbox',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                    case 'double':
                    case 'float':
                    case 'numeric':
                    case 'real':
                        return new FieldIdentifierGuess(
                            'number',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                    case 'integer':
                    case 'int':
                    case 'long':
                        return new FieldIdentifierGuess(
                            'integer',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                    case 'string':
                        return new FieldIdentifierGuess(
                            'text',
                            array(),
                            FieldGuess::LOW_CONFIDENCE
                        );
                    case '\DateTime':
                        return new FieldIdentifierGuess(
                            'date',
                            array(),
                            FieldGuess::MEDIUM_CONFIDENCE
                        );
                }
                break;
            case 'Symfony\Component\Validator\Constraints\Choice':
                return new FieldIdentifierGuess(
                    'choice',
                    array('choices' => $constraint->choices),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Country':
                return new FieldIdentifierGuess(
                    'country',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Date':
                return new FieldIdentifierGuess(
                    'date',
                    array('type' => 'string'),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\DateTime':
                return new FieldIdentifierGuess(
                    'datetime',
                    array('type' => 'string'),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Email':
                return new FieldIdentifierGuess(
                    'text',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\File':
                return new FieldIdentifierGuess(
                    'file',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Image':
                return new FieldIdentifierGuess(
                    'file',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Ip':
                return new FieldIdentifierGuess(
                    'text',
                    array(),
                    FieldGuess::MEDIUM_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Language':
                return new FieldIdentifierGuess(
                    'language',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Locale':
                return new FieldIdentifierGuess(
                    'locale',
                    array(),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Max':
                return new FieldIdentifierGuess(
                    'number',
                    array(),
                    FieldGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\MaxLength':
                return new FieldIdentifierGuess(
                    'text',
                    array(),
                    FieldGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Min':
                return new FieldIdentifierGuess(
                    'number',
                    array(),
                    FieldGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\MinLength':
                return new FieldIdentifierGuess(
                    'text',
                    array(),
                    FieldGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Regex':
                return new FieldIdentifierGuess(
                    'text',
                    array(),
                    FieldGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Time':
                return new FieldIdentifierGuess(
                    'time',
                    array('type' => 'string'),
                    FieldGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Url':
                return new FieldIdentifierGuess(
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