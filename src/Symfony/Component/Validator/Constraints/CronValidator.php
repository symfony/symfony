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

use Cron\CronExpression;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates whether the value is a valid cron expression.
 *
 * @author Erfan Momeni <erfamm5@gmail.com>
 */
class CronValidator extends ConstraintValidator
{
    /**
     * Aliases not understood by every supported version of dragonmantank/cron-expression,
     * mapped to a semantically equivalent alias that is universally supported.
     */
    private const ALIAS_MAP = [
        '@midnight' => '@daily',
    ];

    private const HASH_ALIAS_MAP = [
        '#hourly' => '# * * * *',
        '#daily' => '# # * * *',
        '#weekly' => '# # * * #',
        '#weekly@midnight' => '# #(0-2) * * #',
        '#monthly' => '# # # * *',
        '#monthly@midnight' => '# #(0-2) # * *',
        '#annually' => '# # # # *',
        '#annually@midnight' => '# #(0-2) # # *',
        '#yearly' => '# # # # *',
        '#yearly@midnight' => '# #(0-2) # # *',
        '#midnight' => '# #(0-2) * * *',
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Cron) {
            throw new UnexpectedTypeException($constraint, Cron::class);
        }

        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!$this->isValid(trim($value), $constraint->mode)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Cron::INVALID_FORMAT_ERROR)
                ->addViolation();
        }
    }

    private function isValid(string $expression, string $mode): bool
    {
        if ('' === $expression) {
            return false;
        }

        if ('@' === $expression[0]) {
            if (Cron::MODE_STANDARD === $mode) {
                return false;
            }

            $expression = self::ALIAS_MAP[$expression] ?? $expression;
        }

        if (str_contains($expression, '#')) {
            if (Cron::MODE_HASHED !== $mode) {
                return false;
            }

            $expression = self::resolveHashed($expression);
        }

        return CronExpression::isValidExpression($expression);
    }

    /**
     * Replaces Scheduler-style hash placeholders (`#`, `#(min-max)`) and hash aliases
     * (`#daily`, ...) with concrete values, so the resulting expression can be validated
     * by the underlying cron parser.
     */
    private static function resolveHashed(string $expression): string
    {
        // Field minimums, matching CronExpressionTrigger::HASH_RANGES; used as a safe
        // default when a "#" placeholder is encountered without an explicit range.
        static $fieldMin = [0, 0, 1, 1, 0];

        $expression = self::HASH_ALIAS_MAP[$expression] ?? $expression;
        $parts = explode(' ', $expression);

        if (5 !== \count($parts)) {
            return $expression;
        }

        foreach ($parts as $position => $part) {
            if (preg_match('#^\#(\((\d+)-(\d+)\))?$#', $part, $matches)) {
                $parts[$position] = $matches[2] ?? (string) $fieldMin[$position];
            }
        }

        return implode(' ', $parts);
    }
}
