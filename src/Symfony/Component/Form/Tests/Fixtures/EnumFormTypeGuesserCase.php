<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

class EnumFormTypeGuesserCase
{
    public string $string;
    public UndefinedEnum $undefinedEnum;
    public EnumFormTypeGuesserCaseEnum $enum;
    public ?EnumFormTypeGuesserCaseEnum $nullableEnum;
    public BackedEnumFormTypeGuesserCaseEnum $backedEnum;
    public ?BackedEnumFormTypeGuesserCaseEnum $nullableBackedEnum;
    public EnumFormTypeGuesserCaseEnum|BackedEnumFormTypeGuesserCaseEnum $enumUnion;
    public EnumFormTypeGuesserCaseEnum&BackedEnumFormTypeGuesserCaseEnum $enumIntersection;
}

enum EnumFormTypeGuesserCaseEnum
{
    case Foo;
    case Bar;
}

enum BackedEnumFormTypeGuesserCaseEnum: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}
