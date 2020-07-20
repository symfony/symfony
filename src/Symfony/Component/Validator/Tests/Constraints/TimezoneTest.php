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
use Symfony\Component\Validator\Constraints\Timezone;

/**
 * @author Javier Spagnoletti <phansys@gmail.com>
 */
class TimezoneTest extends TestCase
{
    public function testValidTimezoneConstraints()
    {
        new Timezone();
        new Timezone(['zone' => \DateTimeZone::ALL]);
        new Timezone(\DateTimeZone::ALL_WITH_BC);
        new Timezone([
            'zone' => \DateTimeZone::PER_COUNTRY,
            'countryCode' => 'AR',
        ]);

        $this->addToAssertionCount(1);
    }

    public function testExceptionForGroupedTimezonesByCountryWithWrongZone()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        new Timezone([
            'zone' => \DateTimeZone::ALL,
            'countryCode' => 'AR',
        ]);
    }

    public function testExceptionForGroupedTimezonesByCountryWithoutZone()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        new Timezone(['countryCode' => 'AR']);
    }

    /**
     * @dataProvider provideInvalidZones
     */
    public function testExceptionForInvalidGroupedTimezones(int $zone)
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        new Timezone(['zone' => $zone]);
    }

    public function provideInvalidZones(): iterable
    {
        yield [-1];
        yield [0];
        yield [\DateTimeZone::ALL_WITH_BC + 1];
    }
}
