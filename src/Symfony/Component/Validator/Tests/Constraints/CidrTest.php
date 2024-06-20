<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Cidr;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class CidrTest extends TestCase
{
    public function testForAll()
    {
        $cidrConstraint = new Cidr();

        self::assertEquals(Ip::ALL, $cidrConstraint->version);
        self::assertEquals(0, $cidrConstraint->netmaskMin);
        self::assertEquals(128, $cidrConstraint->netmaskMax);
    }

    public function testForV4()
    {
        $cidrConstraint = new Cidr(['version' => Ip::V4]);

        self::assertEquals(Ip::V4, $cidrConstraint->version);
        self::assertEquals(0, $cidrConstraint->netmaskMin);
        self::assertEquals(32, $cidrConstraint->netmaskMax);
    }

    public function testForV6()
    {
        $cidrConstraint = new Cidr(['version' => Ip::V6]);

        self::assertEquals(Ip::V6, $cidrConstraint->version);
        self::assertEquals(0, $cidrConstraint->netmaskMin);
        self::assertEquals(128, $cidrConstraint->netmaskMax);
    }

    public function testWithInvalidVersion()
    {
        $availableVersions = [
            Ip::V4, Ip::V6, Ip::ALL,
            Ip::V4_NO_PUBLIC, Ip::V6_NO_PUBLIC, Ip::ALL_NO_PUBLIC,
            Ip::V4_NO_PRIVATE, Ip::V6_NO_PRIVATE, Ip::ALL_NO_PRIVATE,
            Ip::V4_NO_RESERVED, Ip::V6_NO_RESERVED, Ip::ALL_NO_RESERVED,
            Ip::V4_ONLY_PUBLIC, Ip::V6_ONLY_PUBLIC, Ip::ALL_ONLY_PUBLIC,
            Ip::V4_ONLY_PRIVATE, Ip::V6_ONLY_PRIVATE, Ip::ALL_ONLY_PRIVATE,
            Ip::V4_ONLY_RESERVED, Ip::V6_ONLY_RESERVED, Ip::ALL_ONLY_RESERVED,
        ];

        self::expectException(ConstraintDefinitionException::class);
        self::expectExceptionMessage(\sprintf('The option "version" must be one of "%s".', implode('", "', $availableVersions)));

        new Cidr(['version' => '8']);
    }

    /**
     * @dataProvider getValidMinMaxValues
     */
    public function testWithValidMinMaxValues(string $ipVersion, int $netmaskMin, int $netmaskMax)
    {
        $cidrConstraint = new Cidr([
            'version' => $ipVersion,
            'netmaskMin' => $netmaskMin,
            'netmaskMax' => $netmaskMax,
        ]);

        self::assertEquals($ipVersion, $cidrConstraint->version);
        self::assertEquals($netmaskMin, $cidrConstraint->netmaskMin);
        self::assertEquals($netmaskMax, $cidrConstraint->netmaskMax);
    }

    /**
     * @dataProvider getInvalidMinMaxValues
     */
    public function testWithInvalidMinMaxValues(string $ipVersion, int $netmaskMin, int $netmaskMax)
    {
        $expectedMax = Ip::V4 == $ipVersion ? 32 : 128;

        self::expectException(ConstraintDefinitionException::class);
        self::expectExceptionMessage(\sprintf('The netmask range must be between 0 and %d.', $expectedMax));

        new Cidr([
            'version' => $ipVersion,
            'netmaskMin' => $netmaskMin,
            'netmaskMax' => $netmaskMax,
        ]);
    }

    public static function getInvalidMinMaxValues(): array
    {
        return [
            [Ip::ALL, -1, 23],
            [Ip::ALL, 23, 130],
            [Ip::ALL, 2, -4],
            [Ip::ALL, -12, -40],
            [Ip::V4, 0, 33],
            [Ip::V4, 2, -10],
            [Ip::V4, -4, 128],
            [Ip::V4, -5, -1],
            [Ip::V6, 5, 200],
            [Ip::V6, -1, 120],
            [Ip::V6, 0, -10],
            [Ip::V6, -15, -20],
        ];
    }

    public static function getValidMinMaxValues(): array
    {
        return [
            [Ip::ALL, 0, 23],
            [Ip::ALL, 23, 120],
            [Ip::V4, 0, 5],
            [Ip::V4, 2, 10],
            [Ip::V6, 0, 43],
            [Ip::V6, 33, 100],
        ];
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(CidrDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(Ip::ALL, $aConstraint->version);
        self::assertSame(0, $aConstraint->netmaskMin);
        self::assertSame(128, $aConstraint->netmaskMax);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(Ip::V6, $bConstraint->version);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(10, $bConstraint->netmaskMin);
        self::assertSame(126, $bConstraint->netmaskMax);
        self::assertSame(['Default', 'CidrDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class CidrDummy
{
    #[Cidr]
    private $a;

    #[Cidr(version: Ip::V6, message: 'myMessage', netmaskMin: 10, netmaskMax: 126)]
    private $b;

    #[Cidr(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
