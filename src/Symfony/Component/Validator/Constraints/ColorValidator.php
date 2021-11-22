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
 * @author Mokhtar Tlili <tlili.mokhtar@gmail.com>
 */
final class ColorValidator extends ConstraintValidator
{
    private const HEX_PATTERN = '/#([a-f0-9]{3}){1,2}\b/i';
    private const RGB_PATTERN = '/^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i';

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof Color) {
            throw new UnexpectedTypeException($constraint, Color::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;
        if ('' === $value) {
            return;
        }

        $types = (array) $constraint->type;

        foreach ($types as $type) {
            $type = strtolower($type);
            $isColorMethod = 'isColor'.ucfirst($type);
            if (method_exists($this, $isColorMethod) && $this->$isColorMethod($value)) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $value)
            ->setParameter('{{ type }}', implode('|', $types))
            ->setCode(Color::INVALID_COLOR_ERROR)
            ->addViolation();
    }

    private function isColorHex($value): bool
    {
        return preg_match(self::HEX_PATTERN, $value) ? true : false;
    }

    private function isColorRgb($value): bool
    {
        return preg_match(self::RGB_PATTERN, $value) ? true : false;
    }
}
