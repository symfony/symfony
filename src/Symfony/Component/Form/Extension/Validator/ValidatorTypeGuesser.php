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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Country;
use Symfony\Component\Validator\Constraints\Currency;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\IsFalse;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Language;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Locale;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Time;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;

class ValidatorTypeGuesser implements FormTypeGuesserInterface
{
    public function __construct(
        private MetadataFactoryInterface $metadataFactory,
    ) {
    }

    public function guessType(string $class, string $property): ?TypeGuess
    {
        return $this->guess($class, $property, $this->guessTypeForConstraint(...));
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
    {
        // If we don't find any constraint telling otherwise, we can assume
        // that a field is not required (with LOW_CONFIDENCE)
        return $this->guess($class, $property, $this->guessRequiredForConstraint(...), false);
    }

    public function guessMaxLength(string $class, string $property): ?ValueGuess
    {
        return $this->guess($class, $property, $this->guessMaxLengthForConstraint(...));
    }

    public function guessPattern(string $class, string $property): ?ValueGuess
    {
        return $this->guess($class, $property, $this->guessPatternForConstraint(...));
    }

    /**
     * Guesses a field class name for a given constraint.
     */
    public function guessTypeForConstraint(Constraint $constraint): ?TypeGuess
    {
        switch ($constraint::class) {
            case Type::class:
                switch ($constraint->type) {
                    case 'array':
                        return new TypeGuess(CollectionType::class, [], Guess::MEDIUM_CONFIDENCE);
                    case 'boolean':
                    case 'bool':
                        return new TypeGuess(CheckboxType::class, [], Guess::MEDIUM_CONFIDENCE);

                    case 'double':
                    case 'float':
                    case 'numeric':
                    case 'real':
                        return new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE);

                    case 'integer':
                    case 'int':
                    case 'long':
                        return new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE);

                    case \DateTime::class:
                    case '\DateTime':
                        return new TypeGuess(DateType::class, [], Guess::MEDIUM_CONFIDENCE);

                    case \DateTimeImmutable::class:
                    case '\DateTimeImmutable':
                    case \DateTimeInterface::class:
                    case '\DateTimeInterface':
                        return new TypeGuess(DateType::class, ['input' => 'datetime_immutable'], Guess::MEDIUM_CONFIDENCE);

                    case 'string':
                        return new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE);
                }
                break;

            case Country::class:
                return new TypeGuess(CountryType::class, [], Guess::HIGH_CONFIDENCE);

            case Currency::class:
                return new TypeGuess(CurrencyType::class, [], Guess::HIGH_CONFIDENCE);

            case Date::class:
                return new TypeGuess(DateType::class, ['input' => 'string'], Guess::HIGH_CONFIDENCE);

            case DateTime::class:
                return new TypeGuess(DateTimeType::class, ['input' => 'string'], Guess::HIGH_CONFIDENCE);

            case Email::class:
                return new TypeGuess(EmailType::class, [], Guess::HIGH_CONFIDENCE);

            case File::class:
            case Image::class:
                $options = [];
                if ($constraint->mimeTypes) {
                    $options = ['attr' => ['accept' => implode(',', (array) $constraint->mimeTypes)]];
                }

                return new TypeGuess(FileType::class, $options, Guess::HIGH_CONFIDENCE);

            case Language::class:
                return new TypeGuess(LanguageType::class, [], Guess::HIGH_CONFIDENCE);

            case Locale::class:
                return new TypeGuess(LocaleType::class, [], Guess::HIGH_CONFIDENCE);

            case Time::class:
                return new TypeGuess(TimeType::class, ['input' => 'string'], Guess::HIGH_CONFIDENCE);

            case Url::class:
                return new TypeGuess(UrlType::class, [], Guess::HIGH_CONFIDENCE);

            case Ip::class:
                return new TypeGuess(TextType::class, [], Guess::MEDIUM_CONFIDENCE);

            case Length::class:
            case Regex::class:
                return new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE);

            case Range::class:
                return new TypeGuess(NumberType::class, [], Guess::LOW_CONFIDENCE);

            case Count::class:
                return new TypeGuess(CollectionType::class, [], Guess::LOW_CONFIDENCE);

            case IsTrue::class:
            case IsFalse::class:
                return new TypeGuess(CheckboxType::class, [], Guess::MEDIUM_CONFIDENCE);
        }

        return null;
    }

    /**
     * Guesses whether a field is required based on the given constraint.
     */
    public function guessRequiredForConstraint(Constraint $constraint): ?ValueGuess
    {
        return match ($constraint::class) {
            NotNull::class,
            NotBlank::class,
            IsTrue::class => new ValueGuess(true, Guess::HIGH_CONFIDENCE),
            default => null,
        };
    }

    /**
     * Guesses a field's maximum length based on the given constraint.
     */
    public function guessMaxLengthForConstraint(Constraint $constraint): ?ValueGuess
    {
        switch ($constraint::class) {
            case Length::class:
                if (is_numeric($constraint->max)) {
                    return new ValueGuess($constraint->max, Guess::HIGH_CONFIDENCE);
                }
                break;

            case Type::class:
                if (\in_array($constraint->type, ['double', 'float', 'numeric', 'real'])) {
                    return new ValueGuess(null, Guess::MEDIUM_CONFIDENCE);
                }
                break;

            case Range::class:
                if (is_numeric($constraint->max)) {
                    return new ValueGuess(\strlen((string) $constraint->max), Guess::LOW_CONFIDENCE);
                }
                break;
        }

        return null;
    }

    /**
     * Guesses a field's pattern based on the given constraint.
     */
    public function guessPatternForConstraint(Constraint $constraint): ?ValueGuess
    {
        switch ($constraint::class) {
            case Length::class:
                if (is_numeric($constraint->min)) {
                    return new ValueGuess(sprintf('.{%s,}', (string) $constraint->min), Guess::LOW_CONFIDENCE);
                }
                break;

            case Regex::class:
                $htmlPattern = $constraint->getHtmlPattern();

                if (null !== $htmlPattern) {
                    return new ValueGuess($htmlPattern, Guess::HIGH_CONFIDENCE);
                }
                break;

            case Range::class:
                if (is_numeric($constraint->min)) {
                    return new ValueGuess(sprintf('.{%s,}', \strlen((string) $constraint->min)), Guess::LOW_CONFIDENCE);
                }
                break;

            case Type::class:
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
     */
    protected function guess(string $class, string $property, \Closure $closure, mixed $defaultValue = null): ?Guess
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
