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
 * Validates whether the value is a valid ORCID (Open Researcher and Contributor ID).
 *
 * @author Dominic Bordelon <dominicbordelon@gmail.com
 *
 * @see http://orcid.org/
 */
class OrcidValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Orcid) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Orcid');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;
        $canonical = $value;

        // http://orcid.org/0000-0001-5000-0007
        // ORCIDs are sometimes expressed as URIs
        if (substr($canonical, 0, 17) === 'http://orcid.org/') {
            $canonical = substr($canonical, 17);
        }

        // 0000-0001-5000-0007
        //     ^    ^    ^
        $readabilityHyphensPresent = (isset($canonical{4}) && '-' === $canonical{4} &&
                                      isset($canonical{9}) && '-' === $canonical{9} &&
                                      isset($canonical{14}) && '-' === $canonical{14});

        if ($readabilityHyphensPresent) {
            // remove hyphens
            $canonical = substr($canonical, 0, 4).substr($canonical, 5, 4).substr($canonical, 10, 4).substr($canonical, 15);
        } elseif ($constraint->requireHyphens) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Orcid::MISSING_HYPHENS_ERROR)
                ->addViolation();

            return;
        }

        $length = strlen($canonical);

        if ($length < 16) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Orcid::TOO_SHORT_ERROR)
                ->addViolation();

            return;
        }

        if ($length > 16) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Orcid::TOO_LONG_ERROR)
                ->addViolation();

            return;
        }

        // 000000015000000X
        // ^^^^^^^^^^^^^^^ digits only
        if (!ctype_digit(substr($canonical, 0, 15))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Orcid::INVALID_CHARACTERS_ERROR)
                ->addViolation();

            return;
        }

        // 000000015000000X
        //                ^ digit, x, or X
        if (!ctype_digit($canonical{15}) && 'x' !== $canonical{15} && 'X' !== $canonical{15}) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Orcid::INVALID_CHARACTERS_ERROR)
                ->addViolation();

            return;
        }

        // 000000015000000X
        //                ^ case-sensitive?
        if ($constraint->caseSensitive && 'x' === $canonical{15}) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Orcid::INVALID_CASE_ERROR)
                ->addViolation();

            return;
        }

        $givenCheckDigit = ($canonical{15} === 'x' || $canonical{15} === 'X') ? 10 : (int) $canonical{15};

        $sum = 0;
        for ($i = 0; $i < 15; ++$i) {
            $sum = ($sum + $canonical{$i}) * 2;
        }
        $remainder = $sum % 11;
        $calculatedChecksum = (12 - $remainder) % 11;

        if ($givenCheckDigit !==  $calculatedChecksum) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Orcid::CHECKSUM_FAILED_ERROR)
                ->addViolation();
        }
    }
}
