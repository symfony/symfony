<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * Wraps errors in forms
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormError
{
    /**
     * @var string
     */
    private $message;

    /**
     * The template for the error message
     * @var string
     */
    protected $messageTemplate;

    /**
     * The parameters that should be substituted in the message template
     * @var array
     */
    protected $messageParameters;

    /**
     * The value for error message pluralization
     * @var integer|null
     */
    protected $messagePluralization;

    /**
     * Constructor
     *
     * Any array key in $messageParameters will be used as a placeholder in
     * $messageTemplate.
     *
     * @param string       $message              The translated error message
     * @param string|null  $messageTemplate      The template for the error message
     * @param array        $messageParameters    The parameters that should be
     *                                           substituted in the message template.
     * @param integer|null $messagePluralization The value for error message pluralization
     *
     * @see \Symfony\Component\Translation\Translator
     */
    public function __construct($message, $messageTemplate = null, array $messageParameters = array(), $messagePluralization = null)
    {
        $this->message = $message;
        $this->messageTemplate = $messageTemplate ?: $message;
        $this->messageParameters = $messageParameters;
        $this->messagePluralization = $messagePluralization;
    }

    /**
     * Returns the error message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
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

    /**
     * Returns the value for error message pluralization.
     *
     * @return integer|null
     */
    public function getMessagePluralization()
    {
        return $this->messagePluralization;
    }
}
