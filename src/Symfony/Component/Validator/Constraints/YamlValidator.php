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
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

/**
 * @author Kev <https://github.com/symfonyaml>
 */
class YamlValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Yaml) {
            throw new UnexpectedTypeException($constraint, Yaml::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        /** @see \Symfony\Component\Yaml\Command\LintCommand::validate() */
        $prevErrorHandler = set_error_handler(function ($level, $message, $file, $line) use (&$prevErrorHandler) {
            if (\E_USER_DEPRECATED === $level) {
                throw new ParseException($message, $this->getParser()->getRealCurrentLineNb() + 1);
            }

            return $prevErrorHandler ? $prevErrorHandler($level, $message, $file, $line) : false;
        });

        try {
            (new Parser())->parse($value, $constraint->flags);
        } catch (ParseException $e) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ error }}', $e->getMessage())
                ->setParameter('{{ line }}', $e->getParsedLine())
                ->setCode(Yaml::INVALID_YAML_ERROR)
                ->addViolation();
        } finally {
            restore_error_handler();
        }
    }
}
