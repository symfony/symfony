<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
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

        if (\in_array(Uid::TYPE_ULID, $constraint->types, true) && \in_array(Uid::TYPE_UUID, $constraint->types, true)) {
            if (Ulid::isValid($value)) {
                return;
            }

            if (!Uuid::isValid($value)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode(Uid::INVALID_UID_ERROR)
                    ->addViolation();

                return;
            }

            $this->validateUuidForVersions($value, $constraint);

            return;
        }

        if (\in_array(Uid::TYPE_ULID, $constraint->types, true)) {
            if (!Ulid::isValid($value)) {
                $this->context->buildViolation($constraint->ulidMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode(Uid::INVALID_ULID_ERROR)
                    ->addViolation();
            }

            return;
        }

        if (!Uuid::isValid($value)) {
            $this->context->buildViolation($constraint->uuidMessage)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Uid::INVALID_UUID_ERROR)
                ->addViolation();

            return;
        }

        $this->validateUuidForVersions($value, $constraint);

    }

    public function validateUuidForVersions(string $value, Uid $constraint): void
    {
        foreach ($constraint->versions as $version) {
            if (Uid::V1 === $version && UuidV1::isValid($value)) {
                return;
            }
            if (Uid::V3 === $version && UuidV3::isValid($value)) {
                return;
            }
            if (Uid::V4 === $version && UuidV4::isValid($value)) {
                return;
            }
            if (Uid::V5 === $version && UuidV5::isValid($value)) {
                return;
            }
            if (Uid::V6 === $version && UuidV6::isValid($value)) {
                return;
            }
        }

        $this->context->buildViolation($constraint->versionsMessage)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->setCode(Uid::INVALID_VERSIONS_ERROR)
            ->addViolation();
    }
}
