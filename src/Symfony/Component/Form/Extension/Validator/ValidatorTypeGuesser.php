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
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;

class ValidatorTypeGuesser implements FormTypeGuesserInterface
{
    private $metadataFactory;

    public function __construct(MetadataFactoryInterface $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function guessType(string $class, string $property)
    {
        return $this->guess($class, $property, function (Constraint $constraint) {
            return $this->guessTypeForConstraint($constraint);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function guessRequired(string $class, string $property)
    {
        return $this->guess($class, $property, function (Constraint $constraint) {
            return $this->guessRequiredForConstraint($constraint);
        // If we don't find any constraint telling otherwise, we can assume
        // that a field is not required (with LOW_CONFIDENCE)
        }, false);
    }

    /**
     * {@inheritdoc}
     */
    public function guessMaxLength(string $class, string $property)
    {
        return $this->guess($class, $property, function (Constraint $constraint) {
            return $this->guessMaxLengthForConstraint($constraint);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function guessPattern(string $class, string $property)
    {
        return $this->guess($class, $property, function (Constraint $constraint) {
            return $this->guessPatternForConstraint($constraint);
        });
    }

    /**
     * Guesses a field class name for a given constraint.
     *
     * @return TypeGuess|null The guessed field class and options
     */
    public function guessTypeForConstraint(Constraint $constraint)
    {
        switch (\get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\Type':
                switch ($constraint->type) {
                    case 'array':
                        return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CollectionType', [], Guess::MEDIUM_CONFIDENCE);
                    case 'boolean':
                    case 'bool':
                        return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CheckboxType', [], Guess::MEDIUM_CONFIDENCE);

                    case 'double':
                    case 'float':
                    case 'numeric':
                    case 'real':
                        return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\NumberType', [], Guess::MEDIUM_CONFIDENCE);

                    case 'integer':
                    case 'int':
                    case 'long':
                        return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\IntegerType', [], Guess::MEDIUM_CONFIDENCE);

                    case '\DateTime':
                        return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateType', [], Guess::MEDIUM_CONFIDENCE);

                    case 'string':
                        return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', [], Guess::LOW_CONFIDENCE);
                }
                break;

            case 'Symfony\Component\Validator\Constraints\Country':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CountryType', [], Guess::HIGH_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Currency':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CurrencyType', [], Guess::HIGH_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Date':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateType', ['input' => 'string'], Guess::HIGH_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\DateTime':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateTimeType', ['input' => 'string'], Guess::HIGH_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Email':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\EmailType', [], Guess::HIGH_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\File':
            case 'Symfony\Component\Validator\Constraints\Image':
                $options = [];
                if ($constraint->mimeTypes) {
                    $options = ['attr' => ['accept' => implode(',', (array) $constraint->mimeTypes)]];
                }

                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\FileType', $options, Guess::HIGH_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Language':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\LanguageType', [], Guess::HIGH_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Locale':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\LocaleType', [], Guess::HIGH_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Time':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TimeType', ['input' => 'string'], Guess::HIGH_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Url':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\UrlType', [], Guess::HIGH_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Ip':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', [], Guess::MEDIUM_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Length':
            case 'Symfony\Component\Validator\Constraints\Regex':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TextType', [], Guess::LOW_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Range':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\NumberType', [], Guess::LOW_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\Count':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CollectionType', [], Guess::LOW_CONFIDENCE);

            case 'Symfony\Component\Validator\Constraints\IsTrue':
            case 'Symfony\Component\Validator\Constraints\IsFalse':
                return new TypeGuess('Symfony\Component\Form\Extension\Core\Type\CheckboxType', [], Guess::MEDIUM_CONFIDENCE);
        }

        return null;
    }

    /**
     * Guesses whether a field is required based on the given constraint.
     *
     * @return ValueGuess|null The guess whether the field is required
     */
    public function guessRequiredForConstraint(Constraint $constraint)
    {
        switch (\get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\NotNull':
            case 'Symfony\Component\Validator\Constraints\NotBlank':
            case 'Symfony\Component\Validator\Constraints\IsTrue':
                return new ValueGuess(true, Guess::HIGH_CONFIDENCE);
        }

        return null;
    }

    /**
     * Guesses a field's maximum length based on the given constraint.
     *
     * @return ValueGuess|null The guess for the maximum length
     */
    public function guessMaxLengthForConstraint(Constraint $constraint)
    {
        switch (\get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\Length':
                if (is_numeric($constraint->max)) {
                    return new ValueGuess($constraint->max, Guess::HIGH_CONFIDENCE);
                }
                break;

            case 'Symfony\Component\Validator\Constraints\Type':
                if (\in_array($constraint->type, ['double', 'float', 'numeric', 'real'])) {
                    return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
                }
                break;

            case 'Symfony\Component\Validator\Constraints\Range':
                if (is_numeric($constraint->max)) {
                    return new ValueGuess(\strlen((string) $constraint->max), Guess::LOW_CONFIDENCE);
                }
                break;
        }

        return null;
    }

    /**
     * Guesses a field's pattern based on the given constraint.
     *
     * @return ValueGuess|null The guess for the pattern
     */
    public function guessPatternForConstraint(Constraint $constraint)
    {
        switch (\get_class($constraint)) {
            case 'Symfony\Component\Validator\Constraints\Length':
                if (is_numeric($constraint->min)) {
                    return new ValueGuess(sprintf('.{%s,}', (string) $constraint->min), Guess::LOW_CONFIDENCE);
                }
                break;

            case 'Symfony\Component\Validator\Constraints\Regex':
                $htmlPattern = $constraint->getHtmlPattern();

                if (null !== $htmlPattern) {
                    return new ValueGuess($htmlPattern, Guess::HIGH_CONFIDENCE);
                }
                break;

            case 'Symfony\Component\Validator\Constraints\Range':
                if (is_numeric($constraint->min)) {
                    return new ValueGuess(sprintf('.{%s,}', \strlen((string) $constraint->min)), Guess::LOW_CONFIDENCE);
                }
                break;

            case 'Symfony\Component\Validator\Constraints\Type':
                if (\in_array($constraint->type, ['double', 'float', 'numeric', 'real'])) {
                    return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
                }
                break;
        }

        return null;
    }

    /**
     * Iterates over the constraints of a property, executes a constraints on
     * them and returns the best guess.
     *
     * @param \Closure $closure      The closure that returns a guess
     *                               for a given constraint
     * @param mixed    $defaultValue The default value assumed if no other value
     *                               can be guessed
     *
     * @return Guess|null The guessed value with the highest confidence
     */
    protected function guess(string $class, string $property, \Closure $closure, $defaultValue = null)
    {
        $guesses = [];
        $classMetadata = $this->metadataFactory->getMetadataFor($class);

        if ($classMetadata instanceof ClassMetadataInterface && $classMetadata->hasPropertyMetadata($property)) {
            foreach ($classMetadata->getPropertyMetadata($property) as $memberMetadata) {
                foreach ($memberMetadata->getConstraints() as $constraint) {
                    if ($guess = $closure($constraint)) {
                        $guesses[] = $guess;
                    }
                }
            }
        }

        if (null !== $defaultValue) {
            $guesses[] = new ValueGuess($defaultValue, Guess::LOW_CONFIDENCE);
        }

        return Guess::getBestGuess($guesses);
    }
}
