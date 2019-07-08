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

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExceptionForGroupedTimezonesByCountryWithWrongZone()
    {
        new Timezone([
            'zone' => \DateTimeZone::ALL,
            'countryCode' => 'AR',
        ]);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExceptionForGroupedTimezonesByCountryWithoutZone()
    {
        new Timezone(['countryCode' => 'AR']);
    }

    /**
     * @dataProvider provideInvalidZones
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExceptionForInvalidGroupedTimezones(int $zone)
    {
        new Timezone(['zone' => $zone]);
    }

    public function provideInvalidZones(): iterable
    {
        yield [-1];
        yield [0];
        yield [\DateTimeZone::ALL_WITH_BC + 1];
    }
}
