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

use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class UidValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Uid) {
            throw new UnexpectedTypeException($constraint, Uid::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        foreach ($constraint->types as $type) {
            if (Uid::UUID_V1 === $type && UuidV1::isValid($value)) {
                return;
            }
            if (Uid::UUID_V3 === $type && UuidV3::isValid($value)) {
                return;
            }
            if (Uid::UUID_V4 === $type && UuidV4::isValid($value)) {
                return;
            }
            if (Uid::UUID_V5 === $type && UuidV5::isValid($value)) {
                return;
            }
            if (Uid::UUID_V6 === $type && UuidV6::isValid($value)) {
                return;
            }
            if (Uid::ULID === $type && Ulid::isValid($value)) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->setCode(Uid::INVALID_UID_ERROR)
            ->addViolation();
    }
}
