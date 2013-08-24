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
 * Default implementation of {@ConstraintViolationInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @since v2.2.0
 */
class ConstraintViolation implements ConstraintViolationInterface
{
    /**
     * @var string
     */
    private $message;

    /**
     * @var string
     */
    private $messageTemplate;

    /**
     * @var array
     */
    private $messageParameters;

    /**
     * @var integer|null
     */
    private $messagePluralization;

    /**
     * @var mixed
     */
    private $root;

    /**
     * @var string
     */
    private $propertyPath;

    /**
     * @var mixed
     */
    private $invalidValue;

    /**
     * @var mixed
     */
    private $code;

    /**
     * Creates a new constraint violation.
     *
     * @param string       $message               The violation message.
     * @param string       $messageTemplate       The raw violation message.
     * @param array        $messageParameters     The parameters to substitute
     *                                            in the raw message.
     * @param mixed        $root                  The value originally passed
     *                                            to the validator.
     * @param string       $propertyPath          The property path from the
     *                                            root value to the invalid
     *                                            value.
     * @param mixed        $invalidValue          The invalid value causing the
     *                                            violation.
     * @param integer|null $messagePluralization  The pluralization parameter.
     * @param mixed        $code                  The error code of the
     *                                            violation, if any.
     *
     * @since v2.2.0
     */
    public function __construct($message, $messageTemplate, array $messageParameters, $root, $propertyPath, $invalidValue, $messagePluralization = null, $code = null)
    {
        $this->message = $message;
        $this->messageTemplate = $messageTemplate;
        $this->messageParameters = $messageParameters;
        $this->messagePluralization = $messagePluralization;
        $this->root = $root;
        $this->propertyPath = $propertyPath;
        $this->invalidValue = $invalidValue;
        $this->code = $code;
    }

    /**
     * Converts the violation into a string for debugging purposes.
     *
     * @return string The violation as string.
     *
     * @since v2.1.0
     */
    public function __toString()
    {
        $class = (string) (is_object($this->root) ? get_class($this->root) : $this->root);
        $propertyPath = (string) $this->propertyPath;
        $code = $this->code;

        if ('' !== $propertyPath && '[' !== $propertyPath[0] && '' !== $class) {
            $class .= '.';
        }

        if (!empty($code)) {
            $code = ' (code '.$code.')';
        }

        return $class.$propertyPath.":\n    ".$this->getMessage().$code;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getMessageParameters()
    {
        return $this->messageParameters;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function getMessagePluralization()
    {
        return $this->messagePluralization;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getPropertyPath()
    {
        return $this->propertyPath;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getInvalidValue()
    {
        return $this->invalidValue;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function getCode()
    {
        return $this->code;
    }
}
