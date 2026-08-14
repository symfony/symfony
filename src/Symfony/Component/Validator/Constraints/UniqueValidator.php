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

use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Yevgeniy Zholkevskiy <zhenya.zholkevskiy@gmail.com>
 */
class UniqueValidator extends ConstraintValidator
{
    public function __construct(
        private ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
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
        foreach ($value as $index => $element) {
            $element = $normalizer($element);

            if ($fields && !((\is_array($element) || \is_object($element)) && $element = $this->reduceElementKeys($fields, $element))) {
                continue;
            }

            if (!\in_array($element, $collectionElements, true)) {
                $collectionElements[] = $element;
                continue;
            }

            $violationBuilder = $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($element))
                ->setCode(Unique::IS_NOT_UNIQUE);

            if (!$constraint->stopOnFirstError || null !== $constraint->errorPath) {
                $violationBuilder->atPath("[$index]".(null !== $constraint->errorPath ? ".{$constraint->errorPath}" : ''));
            }

            $violationBuilder->addViolation();

            if ($constraint->stopOnFirstError) {
                return;
            }
        }
    }

    private function getNormalizer(Unique $unique): callable
    {
        return $unique->normalizer ?? static fn ($value) => $value;
    }

    private function reduceElementKeys(array $fields, array|object $element): array
    {
        $output = [];
        foreach ($fields as $field) {
            if (!\is_string($field)) {
                throw new UnexpectedTypeException($field, 'string');
            }

            $elementAsArray = null;
            // handle public object property
            if (\is_object($element) && property_exists($element, $field)) {
                $elementAsArray = (array) $element;
            } elseif (\is_array($element)) {
                $elementAsArray = $element;
            }

            if ($elementAsArray && \array_key_exists($field, $elementAsArray)) {
                $output[$field] = $elementAsArray[$field];
                continue;
            }

            try {
                $output[$field] = $this->getPropertyAccessor()->getValue($element, $field);
            } catch (AccessException) {
                // fields are optional
            }
        }

        return $output;
    }

    private function getPropertyAccessor(): PropertyAccessorInterface
    {
        if (!class_exists(PropertyAccess::class)) {
            throw new LogicException('Property path requires symfony/property-access package to be installed. Try running "composer require symfony/property-access".');
        }

        return $this->propertyAccessor ??= PropertyAccess::createPropertyAccessor();
    }
}
