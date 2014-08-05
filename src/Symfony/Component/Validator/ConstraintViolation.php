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
    private $parameters;

    /**
     * @var int|null
     */
    private $plural;

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
     * @param string       $message         The violation message
     * @param string       $messageTemplate The raw violation message
     * @param array        $parameters      The parameters to substitute in the
     *                                      raw violation message
     * @param mixed        $root            The value originally passed to the
     *                                      validator
     * @param string       $propertyPath    The property path from the root
     *                                      value to the invalid value
     * @param mixed        $invalidValue    The invalid value that caused this
     *                                      violation
     * @param int|null     $plural          The number for determining the plural
     *                                      form when translating the message
     * @param mixed        $code            The error code of the violation
     */
    public function __construct($message, $messageTemplate, array $parameters, $root, $propertyPath, $invalidValue, $plural = null, $code = null)
    {
        $this->message = $message;
        $this->messageTemplate = $messageTemplate;
        $this->parameters = $parameters;
        $this->plural = $plural;
        $this->root = $root;
        $this->propertyPath = $propertyPath;
        $this->invalidValue = $invalidValue;
        $this->code = $code;
    }

    /**
     * Converts the violation into a string for debugging purposes.
     *
     * @return string The violation as string.
     */
    public function __toString()
    {
        if (is_object($this->root)) {
            $class = 'Object('.get_class($this->root).')';
        } elseif (is_array($this->root)) {
            $class = 'Array';
        } else {
            $class = (string) $this->root;
        }

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
     * {@inheritdoc}
     */
    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageParameters()
    {
        return $this->parameters;
    }

    /**
     * Alias of {@link getMessageParameters()}.
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessagePluralization()
    {
        return $this->plural;
    }

    /**
     * Alias of {@link getMessagePluralization()}.
     */
    public function getPlural()
    {
        return $this->plural;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyPath()
    {
        return $this->propertyPath;
    }

    /**
     * {@inheritdoc}
     */
    public function getInvalidValue()
    {
        return $this->invalidValue;
    }

    /**
     * {@inheritdoc}
     */
    public function getCode()
    {
        return $this->code;
    }
}
