<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * Base class for constraint validators
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
abstract class ConstraintValidator implements ConstraintValidatorInterface
{
    /**
     * @var ExecutionContext
     */
    protected $context;

    /**
     * @var string
     *
     * @deprecated
     */
    private $messageTemplate;

    /**
     * @var array
     *
     * @deprecated
     */
    private $messageParameters;

    /**
     * {@inheritDoc}
     */
    public function initialize(ExecutionContext $context)
    {
        $this->context = $context;
        $this->messageTemplate = '';
        $this->messageParameters = array();
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function getMessageParameters()
    {
        return $this->messageParameters;
    }

    /**
     * Wrapper for $this->context->addViolation()
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    protected function setMessage($template, array $parameters = array())
    {
        $this->messageTemplate = $template;
        $this->messageParameters = $parameters;

        if (!$this->context instanceof ExecutionContext) {
            throw new ValidatorException('ConstraintValidator::initialize() must be called before setting violation messages');
        }

        $this->context->addViolation($template, $parameters);
    }

    /**
     * Stub implementation delegating to the deprecated isValid method.
     *
     * This stub exists for BC and will be dropped in Symfony 2.3.
     *
     * @see ConstraintValidatorInterface::validate
     */
    public function validate($value, Constraint $constraint)
    {
        return $this->isValid($value, $constraint);
    }

    /**
     * BC variant of validate.
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    protected function isValid($value, Constraint $constraint)
    {
    }
}
