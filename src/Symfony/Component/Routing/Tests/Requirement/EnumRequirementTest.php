<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Requirement;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Tests\Fixtures\Enum\TestIntBackedEnum;
use Symfony\Component\Routing\Tests\Fixtures\Enum\TestStringBackedEnum;
use Symfony\Component\Routing\Tests\Fixtures\Enum\TestStringBackedEnum2;
use Symfony\Component\Routing\Tests\Fixtures\Enum\TestUnitEnum;

class EnumRequirementTest extends TestCase
{
    public function testNotABackedEnum()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"Symfony\Component\Routing\Tests\Fixtures\Enum\TestUnitEnum" is not a "BackedEnum" class.');

        new EnumRequirement(TestUnitEnum::class);
    }

    public function testCaseNotABackedEnum()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Case must be a "BackedEnum" instance, "string" given.');

        new EnumRequirement(['wrong']);
    }

    public function testCaseFromAnotherEnum()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"Symfony\Component\Routing\Tests\Fixtures\Enum\TestStringBackedEnum2::Spades" is not a case of "Symfony\Component\Routing\Tests\Fixtures\Enum\TestStringBackedEnum".');

        new EnumRequirement([TestStringBackedEnum::Diamonds, TestStringBackedEnum2::Spades]);
    }

    /**
     * @dataProvider provideToString
     */
    public function testToString(string $expected, string|array $cases = [])
    {
        $this->assertSame($expected, (string) new EnumRequirement($cases));
    }

    public static function provideToString()
    {
        return [
            ['hearts|diamonds|clubs|spades', TestStringBackedEnum::class],
            ['10|20|30|40', TestIntBackedEnum::class],
            ['diamonds|spades', [TestStringBackedEnum::Diamonds, TestStringBackedEnum::Spades]],
            ['diamonds', [TestStringBackedEnum::Diamonds]],
            ['hearts|diamonds|clubs|spa\|des', TestStringBackedEnum2::class],
        ];
    }

    public function testInRoute()
    {
        $this->assertSame([
            'bar' => 'hearts|diamonds|clubs|spades',
        ], (new Route(
            path: '/foo/{bar}',
            requirements: [
                'bar' => new EnumRequirement(TestStringBackedEnum::class),
            ],
        ))->getRequirements());
    }
}
