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
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
class CssColorValidator extends ConstraintValidator
{
    private const PATTERN_HEX_LONG = '/^#[0-9a-f]{6}([0-9a-f]{2})?$/i';
    private const PATTERN_HEX_SHORT = '/^#[0-9a-f]{3,4}$/i';
    private const PATTERN_NAMED_COLORS = '/^(black|red|green|yellow|blue|magenta|cyan|white)/i';

    private const COLOR_PATTERNS = [
        CssColor::VALIDATION_MODE_HEX_LONG => self::PATTERN_HEX_LONG,
        CssColor::VALIDATION_MODE_HEX_SHORT => self::PATTERN_HEX_SHORT,
        CssColor::VALIDATION_MODE_NAMED_COLORS => self::PATTERN_NAMED_COLORS,
    ];

    private $defaultMode;

    public function __construct(string $defaultMode = CssColor::VALIDATION_MODE_HEX_LONG)
    {
        if (!isset(self::COLOR_PATTERNS[$defaultMode])) {
            throw new \InvalidArgumentException('The "defaultMode" parameter value is not valid.');
        }

        $this->defaultMode = $defaultMode;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof CssColor) {
            throw new UnexpectedTypeException($constraint, CssColor::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;
        if ('' === $value) {
            return;
        }

        if (null !== $constraint->normalizer) {
            $value = ($constraint->normalizer)($value);
        }

        if (null === $constraint->mode) {
            $constraint->mode = $this->defaultMode;
        }

        if (!isset(self::COLOR_PATTERNS[$constraint->mode])) {
            throw new \InvalidArgumentException(sprintf('The "%s::$mode" parameter value is not valid.', get_debug_type($constraint)));
        }

        if (!preg_match(self::COLOR_PATTERNS[$constraint->mode], $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(CssColor::INVALID_FORMAT_ERROR)
                ->addViolation();
        }
    }
}
