<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\FieldFactory;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;

/**
 * Guesses form fields from the metadata of the a Validator class
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ValidatorFieldFactoryGuesser implements FieldFactoryGuesserInterface
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
    public function guessClass($class, $property)
    {
        $guesser = $this;

        return $this->guess($class, $property, function (Constraint $constraint) use ($guesser) {
            return $guesser->guessClassForConstraint($constraint);
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
     * @return FieldFactoryGuess  The guessed value with the highest confidence
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

        return FieldFactoryGuess::getBestGuess($guesses);
    }

    /**
     * Guesses a field class name for a given constraint
     *
     * @param  Constraint $constraint  The constraint to guess for
     * @return FieldFactoryClassGuess  The guessed field class and options
     */
    public function guessClassForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\Type':
                switch ($constraint->type) {
                    case 'boolean':
                    case 'bool':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\CheckboxField',
                            array(),
                            FieldFactoryGuess::MEDIUM_CONFIDENCE
                        );
                    case 'double':
                    case 'float':
                    case 'numeric':
                    case 'real':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\NumberField',
                            array(),
                            FieldFactoryGuess::MEDIUM_CONFIDENCE
                        );
                    case 'integer':
                    case 'int':
                    case 'long':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\IntegerField',
                            array(),
                            FieldFactoryGuess::MEDIUM_CONFIDENCE
                        );
                    case 'string':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\TextField',
                            array(),
                            FieldFactoryGuess::LOW_CONFIDENCE
                        );
                    case '\DateTime':
                        return new FieldFactoryClassGuess(
                            'Symfony\Component\Form\DateField',
                            array(),
                            FieldFactoryGuess::MEDIUM_CONFIDENCE
                        );
                }
                break;
            case 'Symfony\Component\Validator\Constraints\Choice':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\ChoiceField',
                    array('choices' => $constraint->choices),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Country':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\CountryField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Date':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\DateField',
                    array('type' => 'string'),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\DateTime':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\DateTimeField',
                    array('type' => 'string'),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Email':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\File':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\FileField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Image':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\FileField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Ip':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array(),
                    FieldFactoryGuess::MEDIUM_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Language':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\LanguageField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Locale':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\LocaleField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Max':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\NumberField',
                    array(),
                    FieldFactoryGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\MaxLength':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array(),
                    FieldFactoryGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Min':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\NumberField',
                    array(),
                    FieldFactoryGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\MinLength':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array(),
                    FieldFactoryGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Regex':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array(),
                    FieldFactoryGuess::LOW_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Time':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TimeField',
                    array('type' => 'string'),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Url':
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\UrlField',
                    array(),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            default:
                return new FieldFactoryClassGuess(
                    'Symfony\Component\Form\TextField',
                    array(),
                    FieldFactoryGuess::LOW_CONFIDENCE
                );
        }
    }

    /**
     * Guesses whether a field is required based on the given constraint
     *
     * @param  Constraint $constraint  The constraint to guess for
     * @return FieldFactoryGuess       The guess whether the field is required
     */
    public function guessRequiredForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\NotNull':
                return new FieldFactoryGuess(
                    true,
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\NotBlank':
                return new FieldFactoryGuess(
                    true,
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            default:
                return new FieldFactoryGuess(
                    false,
                    FieldFactoryGuess::LOW_CONFIDENCE
                );
        }
    }

    /**
     * Guesses a field's maximum length based on the given constraint
     *
     * @param  Constraint $constraint  The constraint to guess for
     * @return FieldFactoryGuess       The guess for the maximum length
     */
    public function guessMaxLengthForConstraint(Constraint $constraint)
    {
        switch (get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\MaxLength':
                return new FieldFactoryGuess(
                    $constraint->limit,
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
            case 'Symfony\Component\Validator\Constraints\Max':
                return new FieldFactoryGuess(
                    strlen((string)$constraint->limit),
                    FieldFactoryGuess::HIGH_CONFIDENCE
                );
        }
    }
}