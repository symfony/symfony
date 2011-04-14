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
 * Represents a single violation of a constraint.
 *
 * @api
 */
class ConstraintViolation
{
    protected $messageTemplate;
    protected $messageParameters;
    protected $messagePluralization;
    protected $root;
    protected $propertyPath;
    protected $invalidValue;

    public function __construct($messageTemplate, array $messageParameters, $root, $propertyPath, $invalidValue, $messagePluralization = null)
    {
        $this->messageTemplate = $messageTemplate;
        $this->messageParameters = $messageParameters;
        $this->messagePluralization = $messagePluralization;
        $this->root = $root;
        $this->propertyPath = $propertyPath;
        $this->invalidValue = $invalidValue;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $class = (string) (is_object($this->root) ? get_class($this->root) : $this->root);
        $propertyPath = (string) $this->propertyPath;

        if ('' !== $propertyPath && '[' !== $propertyPath[0] && '' !== $class) {
            $class .= '.';
        }

        return $class . $propertyPath . ":\n    " . $this->getMessage();
    }

    /**
     * @return string
     *
     * @api
     */
    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    /**
     * @return array
     *
     * @api
     */
    public function getMessageParameters()
    {
        return $this->messageParameters;
    }

    /**
     * @return integer|null
     */
    public function getMessagePluralization()
    {
        return $this->messagePluralization;
    }

    /**
     * Returns the violation message.
     *
     * @return string
     *
     * @api
     */
    public function getMessage()
    {
        $parameters = $this->messageParameters;

        foreach ($parameters as $i => $parameter) {
            if (is_array($parameter)) {
                $parameters[$i] = 'Array';
            }
        }

        return strtr($this->messageTemplate, $parameters);
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function getPropertyPath()
    {
        return $this->propertyPath;
    }

    public function getInvalidValue()
    {
        return $this->invalidValue;
    }
}
