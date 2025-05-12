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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class WordCountValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!class_exists(\IntlBreakIterator::class)) {
            throw new \RuntimeException(\sprintf('The "%s" constraint requires the "intl" PHP extension.', __CLASS__));
        }

        if (!$constraint instanceof WordCount) {
            throw new UnexpectedTypeException($constraint, WordCount::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $iterator = \IntlBreakIterator::createWordInstance($constraint->locale);
        $iterator->setText($value);
        $words = iterator_to_array($iterator->getPartsIterator());

        // erase "blank words" and don't count them as words
        $wordsCount = \count(array_filter(array_map(trim(...), $words), fn ($word) => '' !== $word));

        if (null !== $constraint->min && $wordsCount < $constraint->min) {
            $this->context->buildViolation($constraint->minMessage)
                ->setParameter('{{ count }}', $wordsCount)
                ->setParameter('{{ min }}', $constraint->min)
                ->setPlural($constraint->min)
                ->setInvalidValue($value)
                ->addViolation();
        } elseif (null !== $constraint->max && $wordsCount > $constraint->max) {
            $this->context->buildViolation($constraint->maxMessage)
                ->setParameter('{{ count }}', $wordsCount)
                ->setParameter('{{ max }}', $constraint->max)
                ->setPlural($constraint->max)
                ->setInvalidValue($value)
                ->addViolation();
        }
    }
}
