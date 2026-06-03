<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

/**
 * @author Emil Masiakowski <emil.masiakowski@gmail.com>
 */
class PseudoTypesDummy
{
    public const STRINGS = ['A' => 'A', 'B' => 'B'];
    public const INTEGERS = [1, 2];

    /** @var class-string */
    public $classString;

    /** @var class-string<\stdClass> */
    public $classStringGeneric;

    /** @var html-escaped-string */
    public $htmlEscapedString;

    /** @var lowercase-string */
    public $lowercaseString;

    /** @var non-empty-lowercase-string */
    public $nonEmptyLowercaseString;

    /** @var non-empty-string */
    public $nonEmptyString;

    /** @var numeric-string */
    public $numericString;

    /** @var trait-string */
    public $traitString;

    /** @var positive-int */
    public $positiveInt;

    /** @var literal-string */
    public $literalString;

    /** @var true */
    public $true;

    /** @var false */
    public $false;

    /** @var value-of<self::STRINGS> */
    public $valueOfStrings;

    /** @var value-of<self::INTEGERS> */
    public $valueOfIntegers;

    /** @var value-of<IntEnumDummy> */
    public $valueOfIntEnum;

    /** @var value-of<StringEnumDummy> */
    public $valueOfStringEnum;

    /** @var value-of<IntEnumDummy>|null */
    public $valueOfNullableIntEnum;

    /** @var value-of<StringEnumDummy>|null */
    public $valueOfNullableStringEnum;

    /** @var key-of<self::STRINGS> */
    public $keyOfStrings;

    /** @var key-of<self::INTEGERS> */
    public $keyOfIntegers;

    /** @var array-key */
    public $arrayKey;

    /** @var int-mask<1,2,4> */
    public $intMask;

    /** @var int-mask-of<1|2|4> */
    public $intMaskOf;

    /** @var (T is int ? string : int) */
    public $conditional;

    /** @var self::STRINGS['A'] */
    public $offsetAccess;
}
