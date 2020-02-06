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

final class HexColorValidator extends ConstraintValidator
{
    private const PATTERN_HTML5 = '/^#[0-9a-f]{6}$/i';
    private const PATTERN = '/^#[0-9a-f]{3}(?:[0-9a-f](?:[0-9a-f]{2}(?:[0-9a-f]{2})?)?)?$/i';

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof HexColor) {
            throw new UnexpectedTypeException($constraint, HexColor::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        if (!preg_match($constraint->html5 ? self::PATTERN_HTML5 : self::PATTERN, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(HexColor::INVALID_FORMAT_ERROR)
                ->addViolation();
        }
    }
}
