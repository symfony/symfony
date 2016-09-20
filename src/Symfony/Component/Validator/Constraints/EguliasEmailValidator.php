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

use Egulias\EmailValidator\EmailValidator as Validator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\EmailValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use Egulias\EmailValidator\Validation\RFCValidation;
use Egulias\EmailValidator\Validation\SpoofCheckValidation;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Toni Uebernickel <tuebernickel@gmail.com>
 */
class EguliasEmailValidator extends ConstraintValidator
{
    /**
     * @var Validator
     */
    private $validator;

    /**
     * Constructor.
     *
     * @param Validator|null $validator
     */
    public function __construct(Validator $validator = null)
    {
        if (null == $validator) {
            $validator = new EmailValidator();
        }

        $this->validator = $validator;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof EguliasEmail) {
            throw new UnexpectedTypeException($constraint, EguliasEmail::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (!$this->validator->isValid($value, $this->createValidation($constraint))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setInvalidValue($value)
                ->setCode(EguliasEmail::INVALID_EMAIL)
                ->addViolation()
            ;
        }
    }

    /**
     * Provides the validation represented by the given constraint and its configuration or contract.
     *
     * @param EguliasEmail $constraint
     *
     * @return EmailValidation
     */
    protected function createValidation(EguliasEmail $constraint)
    {
        if (count($constraint->validations)) {
            return new MultipleValidationWithAnd($constraint->validations, $constraint->validationMode);
        }

        $validations = [];
        if ($constraint->suppressRFCWarnings) {
            $validations[] = new RFCValidation();
        } else {
            $validations[] = new NoRFCWarningsValidation();
        }

        if ($constraint->checkDNS) {
            $validations[] = new DNSCheckValidation();
        }

        if ($constraint->checkSpoof) {
            $validations[] = new SpoofCheckValidation();
        }

        return new MultipleValidationWithAnd($validations, $constraint->validationMode);
    }
}
