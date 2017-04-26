<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Translation;

use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Translate constraint violations for a given validator.
 *
 * This service can be used, if the validation framework is not used in
 * conjunction with the form framework to display translated error messages.
 * This is the case whenever you use validations without the form framework.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class TranslatorValidator implements ValidatorInterface
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
      * @var TranslatorInterface
      */
    private $translator;

    public function __construct(ValidatorInterface $validator, TranslatorInterface $translator = null)
    {
        $this->validator  = $validator;
        $this->translator = $translator;
    }

    /**
     * {@inheritDoc}
     */
    public function validate($object, $groups = null)
    {
        return $this->translate($this->validator->validate($object, $groups));
    }

    /**
     * {@inheritDoc}
     */
    public function validateProperty($object, $property, $groups = null)
    {
        return $this->translate($this->validator->validateProperty($object, $property, $groups));
    }

    /**
     * {@inheritDoc}
     */
    public function validatePropertyValue($class, $property, $value, $groups = null)
    {
        return $this->translate($this->validator->validatePropertyValue($class, $property, $value, $groups));
    }

    /**
     * {@inheritDoc}
     */
    public function validateValue($value, Constraint $constraint, $groups = null)
    {
        return $this->translate($this->validator->validateValue($value, $constraint, $groups));
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadataFactory()
    {
        return $this->validator->getMetadataFactory();
    }

    private function translate(ConstraintViolationList $constraintViolationList)
    {
        if ( ! $this->translator) {
            return $constraintViolationList;
        }

        $translatedConstraintViolations = new ConstraintViolationList();

        foreach ($constraintViolationList as $violation) {
            $template = $violation->getMessageTemplate();
            $params = $violation->getMessageParameters();

            $translatedMessage = $violation->getMessagePluralization() === null
                ? $this->translator->trans($template, $params, 'validators')
                : $this->translator->transChoice($template, $violation->getMessagePluralization(), $params, 'validators');

            $translatedConstraintViolations->add(new ConstraintViolation(
                $translatedMessage,
                $violation->getMessageParameters(),
                $violation->getRoot(),
                $violation->getPropertyPath(),
                $violation->getInvalidValue(),
                $violation->getMessagePluralization(),
                $violation->getCode()
            ));
        }

        return $translatedConstraintViolations;
    }
}
