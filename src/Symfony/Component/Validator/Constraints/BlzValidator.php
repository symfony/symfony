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
use malkusch\bav\BAV;
use malkusch\bav\BAVException;

/**
 * @author Markus Malkusch <markus@malkusch.de>
 *
 * @see BAV::isValidBank()
 * @api
 */
class BlzValidator extends ConstraintValidator
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
     * {@inheritdoc}
     * 
     * @throws ValidatorException
     */
    public function validate($value, Constraint $constraint)
    {
        try {
            if (!$constraint instanceof Blz) {
                throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Blz');
            }

            if (null === $value || '' === $value) {
                return;
            }

            if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
                throw new UnexpectedTypeException($value, 'string');
            }

            $value = (string) $value;

            if (!$this->bav->isValidBank($value)) {
                $this->context->addViolation($constraint->message, array('{{ value }}' => $value));
            }
        } catch (BAVException $e) {
            throw new ValidatorException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
