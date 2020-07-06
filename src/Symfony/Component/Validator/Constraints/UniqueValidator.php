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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Yevgeniy Zholkevskiy <zhenya.zholkevskiy@gmail.com>
 */
class UniqueValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Unique) {
            throw new UnexpectedTypeException($constraint, Unique::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_array($value) && !$value instanceof \IteratorAggregate) {
            throw new UnexpectedValueException($value, 'array|IteratorAggregate');
        }

        $collectionElements = [];
        $valueNormalizer = $this->getValueNormalizer($constraint);
        foreach ($value as $element) {
            $element = $valueNormalizer($element);

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

    private function getValueNormalizer(Unique $unique)
    {
        $normalizer = $unique->valueNormalizer;

        if (null === $normalizer) {
            return static function($value) {
                return $value;
            };
        }

        if (is_callable($normalizer)) {
            return $normalizer;
        }

        if (\is_array($normalizer) && !\is_callable($normalizer) && isset($normalizer[0]) && \is_object($normalizer[0])) {
            $normalizer[0] = \get_class($normalizer[0]);

            return $normalizer;
        }

        throw new ConstraintDefinitionException(json_encode($normalizer).' in Unique constraint is not a valid callable.');
    }
}
