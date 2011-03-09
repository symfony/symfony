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
 */
class ConstraintViolation
{
    protected $messageTemplate;
    protected $messageParameters;
    protected $root;
    protected $propertyPath;
    protected $invalidValue;

    public function __construct($messageTemplate, array $messageParameters, $root, $propertyPath, $invalidValue)
    {
        $this->messageTemplate = $messageTemplate;
        $this->messageParameters = $messageParameters;
        $this->root = $root;
        $this->propertyPath = $propertyPath;
        $this->invalidValue = $invalidValue;
    }

    /**
     * @return string
     */
    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    /**
     * @return array
     */
    public function getMessageParameters()
    {
        return $this->messageParameters;
    }

    /**
     * Returns the violation message.
     *
     * @return string
     */
    public function getMessage()
    {
        return str_replace(array_keys($this->messageParameters), array_values($this->messageParameters), $this->messageTemplate);
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