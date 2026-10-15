<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Cloner;

/**
 * Represents the main properties of a PHP variable.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Stub
{
    public const TYPE_REF = 1;
    public const TYPE_STRING = 2;
    public const TYPE_ARRAY = 3;
    public const TYPE_OBJECT = 4;
    public const TYPE_RESOURCE = 5;
    public const TYPE_SCALAR = 6;

    public const STRING_BINARY = 1;
    public const STRING_UTF8 = 2;

    public const ARRAY_ASSOC = 1;
    public const ARRAY_INDEXED = 2;

    public $type = self::TYPE_REF;
    public $class = '';
    public $value;
    public $cut = 0;
    public $handle = 0;
    public $refCount = 0;
    public $position = 0;
    public $attr = [];

    /**
     * @internal
     */
    protected static array $propertyDefaults = [];

    public function __serialize(): array
    {
        static $noDefault = new \stdClass();

        if (self::class === static::class) {
            $data = [];
            foreach ($this as $k => $v) {
                $default = self::$propertyDefaults[$this::class][$k] ??= ($p = new \ReflectionProperty($this, $k))->hasDefaultValue() ? $p->getDefaultValue() : ($p->hasType() ? $noDefault : null);
                if ($noDefault === $default || $default !== $v) {
                    $data[$k] = $v;
                }
            }

            return $data;
        }

        return \Closure::bind(function () use ($noDefault) {
            $data = [];
            foreach ($this as $k => $v) {
                $default = self::$propertyDefaults[$this::class][$k] ??= ($p = new \ReflectionProperty($this, $k))->hasDefaultValue() ? $p->getDefaultValue() : ($p->hasType() ? $noDefault : null);
                if ($noDefault === $default || $default !== $v) {
                    $data[$k] = $v;
                }
            }

            // Private properties of parent classes are not visible from the scope of the child class
            $ownPrivatePrefix = "\0".$this::class."\0";
            foreach ((array) $this as $k => $v) {
                if ("\0" !== ($k[0] ?? '') || '*' === $k[1] || str_starts_with($k, $ownPrivatePrefix)) {
                    continue;
                }
                $i = strrpos($k, "\0");
                $default = self::$propertyDefaults[$this::class][$k] ??= ($p = new \ReflectionProperty(substr($k, 1, $i - 1), substr($k, 1 + $i)))->hasDefaultValue() ? $p->getDefaultValue() : ($p->hasType() ? $noDefault : null);
                if ($noDefault === $default || $default !== $v) {
                    $data[$k] = $v;
                }
            }

            return $data;
        }, $this, $this::class)();
    }
}
