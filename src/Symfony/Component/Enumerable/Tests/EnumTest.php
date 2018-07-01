<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Enumerable\Test;

use Symfony\Component\Enumerable\Enum;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function testGetConstants()
    {
        $enum = $this->getAnonymousEnum();

        $this->assertEquals(array(
            'TEST_ENUM_1' => 1,
            'TEST_ENUM_2' => 2,
        ), $enum::getConstants());
    }

    public function testIsValidName()
    {
        $enum = $this->getAnonymousEnum();

        $this->assertTrue($enum::isValidName('TEST_ENUM_1'));
        $this->assertTrue($enum::isValidName('TEST_ENUM_2'));
        $this->assertFalse($enum::isValidName('UNDEFINED'));
    }

    public function testIsValidValue()
    {
        $enum = $this->getAnonymousEnum();

        $this->assertTrue($enum::isValidValue(1));
        $this->assertTrue($enum::isValidValue(2));
        $this->assertFalse($enum::isValidValue(3));
    }

    private function getAnonymousEnum(): Enum
    {
        return new class() extends Enum {
            const TEST_ENUM_1 = 1;
            const TEST_ENUM_2 = 2;
        };
    }
}
