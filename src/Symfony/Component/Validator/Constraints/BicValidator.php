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
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
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
    // Reference: https://www.iban.com/structure
    private const BIC_COUNTRY_TO_IBAN_COUNTRY_MAP = [
        // FR includes:
        'GF' => 'FR', // French Guiana
        'PF' => 'FR', // French Polynesia
        'TF' => 'FR', // French Southern Territories
        'GP' => 'FR', // Guadeloupe
        'MQ' => 'FR', // Martinique
        'YT' => 'FR', // Mayotte
        'NC' => 'FR', // New Caledonia
        'RE' => 'FR', // Reunion
        'BL' => 'FR', // Saint Barthelemy
        'MF' => 'FR', // Saint Martin (French part)
        'PM' => 'FR', // Saint Pierre and Miquelon
        'WF' => 'FR', // Wallis and Futuna Islands
        // GB includes:
        'JE' => 'GB', // Jersey
        'IM' => 'GB', // Isle of Man
        'GG' => 'GB', // Guernsey
        'VG' => 'GB', // British Virgin Islands
        // FI includes:
        'AX' => 'FI', // Aland Islands
        // ES includes:
        'IC' => 'ES', // Canary Islands
        'EA' => 'ES', // Ceuta and Melilla
    ];

    public function __construct(private ?PropertyAccessor $propertyAccessor = null)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Bic) {
            throw new UnexpectedTypeException($constraint, Bic::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $canonicalize = str_replace(' ', '', $value);

        // the bic must be either 8 or 11 characters long
        if (!\in_array(\strlen($canonicalize), [8, 11], true)) {
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

        $bicCountryCode = substr($canonicalize, 4, 2);
        if (Bic::VALIDATION_MODE_CASE_INSENSITIVE === $constraint->mode) {
            $bicCountryCode = strtoupper($bicCountryCode);
        }
        if (!isset(self::BIC_COUNTRY_TO_IBAN_COUNTRY_MAP[$bicCountryCode]) && !Countries::exists($bicCountryCode)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_COUNTRY_CODE_ERROR)
                ->addViolation();

            return;
        }

        // should contain uppercase characters only in strict mode
        if (Bic::VALIDATION_MODE_STRICT === $constraint->mode && strtoupper($canonicalize) !== $canonicalize) {
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
                throw new ConstraintDefinitionException(\sprintf('Invalid property path "%s" provided to "%s" constraint: ', $path, get_debug_type($constraint)).$e->getMessage(), 0, $e);
            } catch (UninitializedPropertyException) {
                $iban = null;
            }
        }
        if (!$iban) {
            return;
        }
        $ibanCountryCode = substr($iban, 0, 2);
        if (ctype_alpha($ibanCountryCode) && !$this->bicAndIbanCountriesMatch($bicCountryCode, $ibanCountryCode)) {
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
                throw new LogicException('Unable to use property path as the Symfony PropertyAccess component is not installed. Try running "composer require symfony/property-access".');
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
