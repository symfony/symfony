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

/**
 * Base class for constraint validators
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
use Symfony\Component\Validator\Exception\ValidatorException;

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
     * @deprecated
     */
    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated
     */
    public function getMessageParameters()
    {
        return $this->messageParameters;
    }

    /**
     * Wrapper for $this->context->addViolation()
     *
     * @deprecated
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
}
