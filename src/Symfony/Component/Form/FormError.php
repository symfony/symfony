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

use Symfony\Component\Form\Exception\BadMethodCallException;

/**
 * Wraps errors in forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormError
{
    protected $messageTemplate;
    protected $messageParameters;
    protected $messagePluralization;

    private string $message;
    private mixed $cause;

    /**
     * The form that spawned this error.
     */
    private $origin = null;

    /**
     * Any array key in $messageParameters will be used as a placeholder in
     * $messageTemplate.
     *
     * @param string      $message              The translated error message
     * @param string|null $messageTemplate      The template for the error message
     * @param array       $messageParameters    The parameters that should be
     *                                          substituted in the message template
     * @param int|null    $messagePluralization The value for error message pluralization
     * @param mixed       $cause                The cause of the error
     *
     * @see \Symfony\Component\Translation\Translator
     */
    public function __construct(string $message, string $messageTemplate = null, array $messageParameters = [], int $messagePluralization = null, mixed $cause = null)
    {
        $this->message = $message;
        $this->messageTemplate = $messageTemplate ?: $message;
        $this->messageParameters = $messageParameters;
        $this->messagePluralization = $messagePluralization;
        $this->cause = $cause;
    }

    /**
     * Returns the error message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the error message template.
     */
    public function getMessageTemplate(): string
    {
        return $this->messageTemplate;
    }

    /**
     * Returns the parameters to be inserted in the message template.
     */
    public function getMessageParameters(): array
    {
        return $this->messageParameters;
    }

    /**
     * Returns the value for error message pluralization.
     */
    public function getMessagePluralization(): ?int
    {
        return $this->messagePluralization;
    }

    /**
     * Returns the cause of this error.
     */
    public function getCause(): mixed
    {
        return $this->cause;
    }

    /**
     * Sets the form that caused this error.
     *
     * This method must only be called once.
     *
     * @throws BadMethodCallException If the method is called more than once
     */
    public function setOrigin(FormInterface $origin)
    {
        if (null !== $this->origin) {
            throw new BadMethodCallException('setOrigin() must only be called once.');
        }

        $this->origin = $origin;
    }

    /**
     * Returns the form that caused this error.
     */
    public function getOrigin(): ?FormInterface
    {
        return $this->origin;
    }
}
