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

use Symfony\Component\HttpKernel\Attribute\ArgumentInterface;

/**
 * Responsible for storing metadata of an argument.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
class ArgumentMetadata
{
    public const IS_INSTANCEOF = 2;

    private $name;
    private $type;
    private $isVariadic;
    private $hasDefaultValue;
    private $defaultValue;
    private $isNullable;
    private $attributes;

    /**
     * @param object[] $attributes
     */
    public function __construct(string $name, ?string $type, bool $isVariadic, bool $hasDefaultValue, $defaultValue, bool $isNullable = false, $attributes = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->isVariadic = $isVariadic;
        $this->hasDefaultValue = $hasDefaultValue;
        $this->defaultValue = $defaultValue;
        $this->isNullable = $isNullable || null === $type || ($hasDefaultValue && null === $defaultValue);

        if (null === $attributes || $attributes instanceof ArgumentInterface) {
            trigger_deprecation('symfony/http-kernel', '5.3', 'The "%s" constructor expects an array of PHP attributes as last argument, %s given.', __CLASS__, get_debug_type($attributes));
            $attributes = $attributes ? [$attributes] : [];
        }

        $this->attributes = $attributes;
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
     * @return string|null
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
     * Returns whether the argument accepts null values.
     *
     * @return bool
     */
    public function isNullable()
    {
        return $this->isNullable;
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
            throw new \LogicException(sprintf('Argument $%s does not have a default value. Use "%s::hasDefaultValue()" to avoid this exception.', $this->name, __CLASS__));
        }

        return $this->defaultValue;
    }

    /**
     * Returns the attribute (if any) that was set on the argument.
     */
    public function getAttribute(): ?ArgumentInterface
    {
        trigger_deprecation('symfony/http-kernel', '5.3', 'Method "%s()" is deprecated, use "getAttributes()" instead.', __METHOD__);

        if (!$this->attributes) {
            return null;
        }

        return $this->attributes[0] instanceof ArgumentInterface ? $this->attributes[0] : null;
    }

    /**
     * @return object[]
     */
    public function getAttributes(string $name = null, int $flags = 0): array
    {
        if (!$name) {
            return $this->attributes;
        }

        $attributes = [];
        if ($flags & self::IS_INSTANCEOF) {
            foreach ($this->attributes as $attribute) {
                if ($attribute instanceof $name) {
                    $attributes[] = $attribute;
                }
            }
        } else {
            foreach ($this->attributes as $attribute) {
                if (\get_class($attribute) === $name) {
                    $attributes[] = $attribute;
                }
            }
        }

        return $attributes;
    }
}
