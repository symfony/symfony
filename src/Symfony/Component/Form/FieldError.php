<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Wraps errors in form fields
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FieldError
{
    protected $messageTemplate;

    protected $messageParameters;

    /**
     * Constructor
     *
     * @param string $messageTemplate      The template for the error message
     * @param array $messageParameters     The parameters that should be
     *                                     substituted in the message template.
     */
    public function __construct($messageTemplate, array $messageParameters = array())
    {
        $this->messageTemplate = $messageTemplate;
        $this->messageParameters = $messageParameters;
    }

    /**
     * Returns the error message template
     *
     * @return string
     */
    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    /**
     * Returns the parameters to be inserted in the message template
     *
     * @return array
     */
    public function getMessageParameters()
    {
        return $this->messageParameters;
    }
}