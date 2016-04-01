<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\ControllerMetadata;

/**
 * Responsible for storing metadata of an argument.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
class ArgumentMetadata
{
    private $name;
    private $type;
    private $isVariadic;
    private $hasDefaultValue;
    private $defaultValue;

    /**
     * @param string $name
     * @param string $type
     * @param bool   $isVariadic
     * @param bool   $hasDefaultValue
     * @param mixed  $defaultValue
     */
    public function __construct($name, $type, $isVariadic, $hasDefaultValue, $defaultValue)
    {
        $this->name = $name;
        $this->type = $type;
        $this->isVariadic = $isVariadic;
        $this->hasDefaultValue = $hasDefaultValue;
        $this->defaultValue = $defaultValue;
    }

    /**
     * Returns the name as given in PHP, $foo would yield "foo".
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the type of the argument.
     *
     * The type is the PHP class in 5.5+ and additionally the basic type in PHP 7.0+.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns whether the argument is defined as "...$variadic".
     *
     * @return bool
     */
    public function isVariadic()
    {
        return $this->isVariadic;
    }

    /**
     * Returns whether the argument has a default value.
     *
     * Implies whether an argument is optional.
     *
     * @return bool
     */
    public function hasDefaultValue()
    {
        return $this->hasDefaultValue;
    }

    /**
     * Returns the default value of the argument.
     *
     * @throws \LogicException if no default value is present; {@see self::hasDefaultValue()}
     *
     * @return mixed
     */
    public function getDefaultValue()
    {
        if (!$this->hasDefaultValue) {
            throw new \LogicException(sprintf('Argument $%s does not have a default value. Use %s::hasDefaultValue() to avoid this exception.', $this->name, __CLASS__));
        }

        return $this->defaultValue;
    }
}
