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

    public int $type = self::TYPE_REF;
    public string|int|null $class = '';
    public mixed $value;
    public int $cut = 0;
    public int $handle = 0;
    public int $refCount = 0;
    public int $position = 0;
    public array $attr = [];

    private static array $defaultProperties = [];

    /**
     * @internal
     */
    public function __sleep(): array
    {
        $properties = [];

        if (!isset(self::$defaultProperties[$c = static::class])) {
            self::$defaultProperties[$c] = get_class_vars($c);

            foreach ((new \ReflectionClass($c))->getStaticProperties() as $k => $v) {
                unset(self::$defaultProperties[$c][$k]);
            }
        }

        foreach (self::$defaultProperties[$c] as $k => $v) {
            if ($this->$k !== $v) {
                $properties[] = $k;
            }
        }

        return $properties;
    }
}
