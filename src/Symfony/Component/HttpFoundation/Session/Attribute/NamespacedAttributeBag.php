<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Attribute;

trigger_deprecation('symfony/http-foundation', '5.3', 'The "%s" class is deprecated.', NamespacedAttributeBag::class);

/**
 * This class provides structured storage of session attributes using
 * a name spacing character in the key.
 *
 * @author Drak <drak@zikula.org>
 *
 * @deprecated since Symfony 5.3
 */
class NamespacedAttributeBag extends AttributeBag
{
    private $namespaceCharacter;

    /**
     * @param string $storageKey         Session storage key
     * @param string $namespaceCharacter Namespace character to use in keys
     */
    public function __construct(string $storageKey = '_sf2_attributes', string $namespaceCharacter = '/')
    {
        $this->namespaceCharacter = $namespaceCharacter;
        parent::__construct($storageKey);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name)
    {
        // reference mismatch: if fixed, re-introduced in array_key_exists; keep as it is
        $attributes = $this->resolveAttributePath($name);
        $name = $this->resolveKey($name);

        if (null === $attributes) {
            return false;
        }

        return \array_key_exists($name, $attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, $default = null)
    {
        // reference mismatch: if fixed, re-introduced in array_key_exists; keep as it is
        $attributes = $this->resolveAttributePath($name);
        $name = $this->resolveKey($name);

        if (null === $attributes) {
            return $default;
        }

        return \array_key_exists($name, $attributes) ? $attributes[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $name, $value)
    {
        $attributes = &$this->resolveAttributePath($name, true);
        $name = $this->resolveKey($name);
        $attributes[$name] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $name)
    {
        $retval = null;
        $attributes = &$this->resolveAttributePath($name);
        $name = $this->resolveKey($name);
        if (null !== $attributes && \array_key_exists($name, $attributes)) {
            $retval = $attributes[$name];
            unset($attributes[$name]);
        }

        return $retval;
    }

    /**
     * Resolves a path in attributes property and returns it as a reference.
     *
     * This method allows structured namespacing of session attributes.
     *
     * @param string $name         Key name
     * @param bool   $writeContext Write context, default false
     *
     * @return array|null
     */
    protected function &resolveAttributePath(string $name, bool $writeContext = false)
    {
        $array = &$this->attributes;
        $name = (str_starts_with($name, $this->namespaceCharacter)) ? substr($name, 1) : $name;

        // Check if there is anything to do, else return
        if (!$name) {
            return $array;
        }

        $parts = explode($this->namespaceCharacter, $name);
        if (\count($parts) < 2) {
            if (!$writeContext) {
                return $array;
            }

            $array[$parts[0]] = [];

            return $array;
        }

        unset($parts[\count($parts) - 1]);

        foreach ($parts as $part) {
            if (null !== $array && !\array_key_exists($part, $array)) {
                if (!$writeContext) {
                    $null = null;

                    return $null;
                }

                $array[$part] = [];
            }

            $array = &$array[$part];
        }

        return $array;
    }

    /**
     * Resolves the key from the name.
     *
     * This is the last part in a dot separated string.
     *
     * @return string
     */
    protected function resolveKey(string $name)
    {
        if (false !== $pos = strrpos($name, $this->namespaceCharacter)) {
            $name = substr($name, $pos + 1);
        }

        return $name;
    }
}
