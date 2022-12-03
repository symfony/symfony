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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TypeValidator extends ConstraintValidator
{
    private const VALIDATION_FUNCTIONS = [
        'bool' => 'is_bool',
        'boolean' => 'is_bool',
        'int' => 'is_int',
        'integer' => 'is_int',
        'long' => 'is_int',
        'float' => 'is_float',
        'double' => 'is_float',
        'real' => 'is_float',
        'numeric' => 'is_numeric',
        'string' => 'is_string',
        'scalar' => 'is_scalar',
        'array' => 'is_array',
        'iterable' => 'is_iterable',
        'countable' => 'is_countable',
        'callable' => 'is_callable',
        'object' => 'is_object',
        'resource' => 'is_resource',
        'null' => 'is_null',
        'alnum' => 'ctype_alnum',
        'alpha' => 'ctype_alpha',
        'cntrl' => 'ctype_cntrl',
        'digit' => 'ctype_digit',
        'graph' => 'ctype_graph',
        'lower' => 'ctype_lower',
        'print' => 'ctype_print',
        'punct' => 'ctype_punct',
        'space' => 'ctype_space',
        'upper' => 'ctype_upper',
        'xdigit' => 'ctype_xdigit',
    ];

    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof Type) {
            throw new UnexpectedTypeException($constraint, Type::class);
        }

        if (null === $value) {
            return;
        }

        $types = (array) $constraint->type;

        foreach ($types as $type) {
            $type = strtolower($type);
            if (isset(self::VALIDATION_FUNCTIONS[$type]) && self::VALIDATION_FUNCTIONS[$type]($value)) {
                return;
            }

            if ($value instanceof $type) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->setParameter('{{ type }}', implode('|', $types))
            ->setCode(Type::INVALID_TYPE_ERROR)
            ->addViolation();
    }
}
