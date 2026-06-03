<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

final class DummyWithConstants
{
    public const DUMMY_STRING_A = 'a';
    public const DUMMY_INT_A = 1;
    public const DUMMY_FLOAT_A = 1.23;
    public const DUMMY_TRUE_A = true;
    public const DUMMY_FALSE_A = false;
    public const DUMMY_NULL_A = null;
    public const DUMMY_ARRAY_A = [];
    public const DUMMY_ENUM_A = DummyEnum::ONE;

    public const DUMMY_MIX_1 = self::DUMMY_STRING_A;
    public const DUMMY_MIX_2 = self::DUMMY_INT_A;
    public const DUMMY_MIX_3 = self::DUMMY_FLOAT_A;
    public const DUMMY_MIX_4 = self::DUMMY_TRUE_A;
    public const DUMMY_MIX_5 = self::DUMMY_FALSE_A;
    public const DUMMY_MIX_6 = self::DUMMY_NULL_A;
    public const DUMMY_MIX_7 = self::DUMMY_ARRAY_A;
    public const DUMMY_MIX_8 = self::DUMMY_ENUM_A;
}
