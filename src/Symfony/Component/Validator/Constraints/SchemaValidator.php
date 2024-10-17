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
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

/**
 * @author Benjamin Georgeault <bgeorgeault@wedgesama.fr>
 */
class SchemaValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Schema) {
            throw new UnexpectedTypeException($constraint, Schema::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        try {
            $data = match ($constraint->format) {
                Schema::YAML => $this->validateAndGetYaml($value, $constraint),
                Schema::JSON => $this->validateAndGetJson($value, $constraint),
            };
        } catch (ValidatorException $e) {
            $this->context->buildViolation($constraint->invalidMessage)
                ->setParameter('{{ error }}', $e->getMessage())
                ->setParameter('{{ format }}', $constraint->format)
                ->setCode(Schema::INVALID_ERROR)
                ->addViolation();

            return;
        }

        if (empty($constraint->constraints)) {
            return;
        }

        $validator = ($context = $this->context)
            ->getValidator()->inContext($context);

        $validator->validate($data, $constraint->constraints);
    }

    private function validateAndGetYaml(string $value, Schema $constraint): mixed
    {
        try {
            return (new Parser())->parse($value, $constraint->flags);
        } catch (ParseException $e) {
            throw new ValidatorException(\sprintf('Invalid YAML with message "%s".', $e->getMessage()));
        } finally {
            restore_error_handler();
        }
    }

    private function validateAndGetJson(string $value, Schema $constraint): mixed
    {
        if (!json_validate($value, $constraint->depth ?? 512, $constraint->flags)) {
            throw new ValidatorException('Invalid JSON.');
        }

        return json_decode($value, true, $constraint->depth ?? 512, $constraint->flags);
    }
}
