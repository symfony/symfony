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

use Symfony\Component\Intl\Countries;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Michael Hirschler <michael.vhirsch@gmail.com>
 *
 * @see https://en.wikipedia.org/wiki/ISO_9362#Structure
 */
class BicValidator extends ConstraintValidator
{
    private const BIC_COUNTRY_TO_IBAN_COUNTRY_MAP = [
        // Reference: https://www.ecbs.org/iban/france-bank-account-number.html
        'GF' => 'FR', // French Guiana
        'PF' => 'FR', // French Polynesia
        'TF' => 'FR', // French Southern Territories
        'GP' => 'FR', // Guadeloupe
        'MQ' => 'FR', // Martinique
        'YT' => 'FR', // Mayotte
        'NC' => 'FR', // New Caledonia
        'RE' => 'FR', // Reunion
        'PM' => 'FR', // Saint Pierre and Miquelon
        'WF' => 'FR', // Wallis and Futuna Islands
        // Reference: https://www.ecbs.org/iban/united-kingdom-uk-bank-account-number.html
        'JE' => 'GB', // Jersey
        'IM' => 'GB', // Isle of Man
        'GG' => 'GB', // Guernsey
        'VG' => 'GB', // British Virgin Islands
    ];

    private $propertyAccessor;

    public function __construct(PropertyAccessor $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Bic) {
            throw new UnexpectedTypeException($constraint, Bic::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        $canonicalize = str_replace(' ', '', $value);

        // the bic must be either 8 or 11 characters long
        if (!\in_array(\strlen($canonicalize), [8, 11])) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_LENGTH_ERROR)
                ->addViolation();

            return;
        }

        // must contain alphanumeric values only
        if (!ctype_alnum($canonicalize)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_CHARACTERS_ERROR)
                ->addViolation();

            return;
        }

        // first 4 letters must be alphabetic (bank code)
        if (!ctype_alpha(substr($canonicalize, 0, 4))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_BANK_CODE_ERROR)
                ->addViolation();

            return;
        }

        // @deprecated since Symfony 4.2, will throw in 5.0
        if (class_exists(Countries::class)) {
            $validCountryCode = Countries::exists(substr($canonicalize, 4, 2));
        } else {
            $validCountryCode = ctype_alpha(substr($canonicalize, 4, 2));
            // throw new LogicException('The Intl component is required to use the Bic constraint. Try running "composer require symfony/intl".');
        }

        if (!$validCountryCode) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_COUNTRY_CODE_ERROR)
                ->addViolation();

            return;
        }

        // should contain uppercase characters only
        if (strtoupper($canonicalize) !== $canonicalize) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_CASE_ERROR)
                ->addViolation();

            return;
        }

        // check against an IBAN
        $iban = $constraint->iban;
        $path = $constraint->ibanPropertyPath;
        if ($path && null !== $object = $this->context->getObject()) {
            try {
                $iban = $this->getPropertyAccessor()->getValue($object, $path);
            } catch (NoSuchPropertyException $e) {
                throw new ConstraintDefinitionException(sprintf('Invalid property path "%s" provided to "%s" constraint: ', $path, \get_class($constraint)).$e->getMessage(), 0, $e);
            }
        }
        if (!$iban) {
            return;
        }
        $ibanCountryCode = substr($iban, 0, 2);
        if (ctype_alpha($ibanCountryCode) && !$this->bicAndIbanCountriesMatch(substr($canonicalize, 4, 2), $ibanCountryCode)) {
            $this->context->buildViolation($constraint->ibanMessage)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setParameter('{{ iban }}', $iban)
                ->setCode(Bic::INVALID_IBAN_COUNTRY_CODE_ERROR)
                ->addViolation();
        }
    }

    private function getPropertyAccessor(): PropertyAccessor
    {
        if (null === $this->propertyAccessor) {
            if (!class_exists(PropertyAccess::class)) {
                throw new LogicException('Unable to use property path as the Symfony PropertyAccess component is not installed.');
            }
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor;
    }

    private function bicAndIbanCountriesMatch(string $bicCountryCode, string $ibanCountryCode): bool
    {
        return $ibanCountryCode === $bicCountryCode || $ibanCountryCode === (self::BIC_COUNTRY_TO_IBAN_COUNTRY_MAP[$bicCountryCode] ?? null);
    }
}
