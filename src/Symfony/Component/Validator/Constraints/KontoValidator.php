<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Markus Malkusch <markus@malkusch.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\GetterMetadata;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use malkusch\bav\BAV;
use malkusch\bav\BAVException;

/**
 * @author Markus Malkusch <markus@malkusch.de>
 *
 * @see BAV::isValidBankAccount()
 * @api
 */
class KontoValidator extends ConstraintValidator
{
    /**
     * @var BAV
     */
    private $bav;
    
    /**
     * {@inheritdoc}
     * 
     * @throws ValidatorException
     */
    public function initialize(ExecutionContextInterface $context)
    {
        try {
            parent::initialize($context);
            $this->bav = new BAV();
        } catch (BAVException $e) {
            throw new ValidatorException($e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * @return string
     * @throws UnexpectedTypeException
     */
    private function getScalarProperty($object, $property)
    {
        try {
            $metadata = new GetterMetadata(get_class($object), $property);
            $value = $metadata->getPropertyValue($object);
        } catch (ValidatorException $e) {
            try {
                $metadata = new PropertyMetadata(get_class($object), $property);
                $value = $metadata->getPropertyValue($object);
            } catch (ValidatorException $e2) {
                if (isset($object->$property)) {
                    $value = $object->$property;
                } else {
                    throw $e;
                }
            }
        }
        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }
        return (string) $value;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @throws ValidatorException
     */
    public function validate($value, Constraint $constraint)
    {
        try {
            if (!$constraint instanceof Konto) {
                throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Konto');
            }

            if (null === $value) {
                return;
            }

            if (!is_object($value)) {
                throw new UnexpectedTypeException($value, 'object');
            }
            
            $blz = $this->getScalarProperty($value, $constraint->blz);
            $konto = $this->getScalarProperty($value, $constraint->konto);
            
            if (empty($konto) && empty($blz)) {
                return;
            }
            
            if (!$this->bav->isValidBankAccount($blz, $konto)) {
                $this->context->addViolation($constraint->message, array('{{ value }}' => $konto));
            }
        } catch (BAVException $e) {
            throw new ValidatorException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
