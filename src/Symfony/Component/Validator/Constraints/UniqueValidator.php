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
 * @author Yevgeniy Zholkevskiy <zhenya.zholkevskiy@gmail.com>
 */
class UniqueValidator extends ConstraintValidator
{
    /**
     * @return void
     */
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof Unique) {
            throw new UnexpectedTypeException($constraint, Unique::class);
        }

        $fields = (array) $constraint->fields;

        if (null === $value) {
            return;
        }

        if (!\is_array($value) && !$value instanceof \IteratorAggregate) {
            throw new UnexpectedValueException($value, 'array|IteratorAggregate');
        }

        $collectionElements = [];
        $normalizer = $this->getNormalizer($constraint);
        foreach ($value as $element) {
            $element = $normalizer($element);

            if ($fields && !$element = $this->reduceElementKeys($fields, $element)) {
                continue;
            }

            if (\in_array($element, $collectionElements, true)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode(Unique::IS_NOT_UNIQUE)
                    ->addViolation();

                return;
            }
            $collectionElements[] = $element;
        }
    }

    private function getNormalizer(Unique $unique): callable
    {
        if (null === $unique->normalizer) {
            return static fn ($value) => $value;
        }

        return $unique->normalizer;
    }

    private function reduceElementKeys(array $fields, array $element): array
    {
        $output = [];
        foreach ($fields as $field) {
            if (!\is_string($field)) {
                throw new UnexpectedTypeException($field, 'string');
            }
            if (\array_key_exists($field, $element)) {
                $output[$field] = $element[$field];
            }
        }

        return $output;
    }
}
