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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

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
        $this->expectException(ConstraintDefinitionException::class);
        new Timezone([
            'zone' => \DateTimeZone::ALL,
            'countryCode' => 'AR',
        ]);
    }

    public function testExceptionForGroupedTimezonesByCountryWithoutZone()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new Timezone(['countryCode' => 'AR']);
    }

    /**
     * @dataProvider provideInvalidZones
     */
    public function testExceptionForInvalidGroupedTimezones(int $zone)
    {
        $this->expectException(ConstraintDefinitionException::class);
        new Timezone(['zone' => $zone]);
    }

    public static function provideInvalidZones(): iterable
    {
        yield [-1];
        yield [0];
        yield [\DateTimeZone::ALL_WITH_BC + 1];
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(TimezoneDummy::class);
        self::assertTrue((new AnnotationLoader())->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        self::assertSame(\DateTimeZone::ALL, $aConstraint->zone);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame(\DateTimeZone::PER_COUNTRY, $bConstraint->zone);
        self::assertSame('DE', $bConstraint->countryCode);
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'TimezoneDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);
    }
}

class TimezoneDummy
{
    #[Timezone]
    private $a;

    #[Timezone(zone: \DateTimeZone::PER_COUNTRY, countryCode: 'DE', message: 'myMessage')]
    private $b;

    #[Timezone(groups: ['my_group'], payload: 'some attached data')]
    private $c;
}
