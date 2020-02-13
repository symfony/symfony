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
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class RgbColorValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof RgbColor) {
            throw new UnexpectedTypeException($constraint, RgbColor::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;
        
        $pattern = sprintf('/^rgb%s\((\d{1,3}),\s*(\d{1,3}),\s*(\d{1,3})%s\)$/%s', $constraint->allowAlpha ? 'a?' : '', $constraint->allowAlpha ? '(,\s*[0-1]{0,1}(?:\.\d{1,'.$constraint->alphaPrecision.'})?)?' : '', $constraint->lowerCaseOnly ? '' : 'i');

        if (!preg_match($pattern, $value, $matches) || !$this->areRangesValid($matches)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(RgbColor::INVALID_FORMAT_ERROR)
                ->addViolation();
        }
    }
    
    private function areRangesValid(array $matches): bool
    {
        for ($i = 1; $i < 4; $i++) {
            if (0 > $matches[$i] || 255 < $matches[$i]) {
                return false;
            }
        }
        
        if ($alpha = trim($matches[4] ?? null, ',')) {
            if (0 > $alpha || 1 < $alpha) {
                return false;
            }
        }
        
        return true;
    }
}
