<?php

namespace Symfony\Component\Validator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

abstract class ConstraintValidator implements ConstraintValidatorInterface
{
    protected $context;
    private $messageTemplate;
    private $messageParameters;

    /**
     * {@inheritDoc}
     */
    public function initialize(ValidationContext $context)
    {
        $this->context = $context;
        $this->messageTemplate = '';
        $this->messageParameters = array();
    }

    /**
     * {@inheritDoc}
     */
    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    /**
     * {@inheritDoc}
     */
    public function getMessageParameters()
    {
        return $this->messageParameters;
    }

    protected function setMessage($template, array $parameters = array())
    {
        $this->messageTemplate = $template;
        $this->messageParameters = $parameters;
    }
}