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
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Type) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Type');
        }

        if (null === $value) {
            return;
        }

        $types = (array) $constraint->type;

        foreach ($types as $type) {
            $type = strtolower($type);
            $type = 'boolean' === $type ? 'bool' : $type;
            $isFunction = 'is_'.$type;
            $ctypeFunction = 'ctype_'.$type;
            if (\function_exists($isFunction) && $isFunction($value)) {
                return;
            } elseif (\function_exists($ctypeFunction) && $ctypeFunction($value)) {
                return;
            } elseif ($value instanceof $type) {
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
