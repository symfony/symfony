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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use malkusch\bav\BAV;
use malkusch\bav\BAVException;

/**
 * @author Markus Malkusch <markus@malkusch.de>
 *
 * @see BAV::isValidBIC()
 * @api
 */
class BicValidator extends ConstraintValidator
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
     * @throws ConstraintDefinitionException
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Bic) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Bic');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        switch ($constraint->country) {
            case Bic::DE:
                try {
                    if (!$this->bav->isValidBIC($value)) {
                        $this->context->addViolation($constraint->message, array('{{ value }}' => $value));
                    }
                } catch (BAVException $e) {
                    throw new ValidatorException($e->getMessage(), $e->getCode(), $e);
                }
                break;

            default:
                //TODO implement a generic BIC validation.
                throw new ConstraintDefinitionException(
                    "BIC validation for country '$constraint->country' is not implemented"
                );
        }
    }
}
