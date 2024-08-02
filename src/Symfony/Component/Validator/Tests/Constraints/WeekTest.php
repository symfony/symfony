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
use Symfony\Component\Validator\Constraints\Week;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class WeekTest extends TestCase
{
    public function testWithoutArgument()
    {
        $week = new Week();

        $this->assertNull($week->min);
        $this->assertNull($week->max);
    }

    public function testConstructor()
    {
        $week = new Week(min: '2010-W01', max: '2010-W02');

        $this->assertSame('2010-W01', $week->min);
        $this->assertSame('2010-W02', $week->max);
    }

    public function testMinYearIsAfterMaxYear()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\Week" constraint requires the min week to be less than or equal to the max week.');

        new Week(min: '2011-W01', max: '2010-W02');
    }

    public function testMinWeekIsAfterMaxWeek()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\Week" constraint requires the min week to be less than or equal to the max week.');

        new Week(min: '2010-W02', max: '2010-W01');
    }

    public function testMinAndMaxWeeksAreTheSame()
    {
        $week = new Week(min: '2010-W01', max: '2010-W01');

        $this->assertSame('2010-W01', $week->min);
        $this->assertSame('2010-W01', $week->max);
    }

    public function testMinIsBadlyFormatted()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\Week" constraint requires the min week to be in the ISO 8601 format if set.');

        new Week(min: '2010-01');
    }

    public function testMaxIsBadlyFormatted()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\Week" constraint requires the max week to be in the ISO 8601 format if set.');

        new Week(max: '2010-01');
    }

    public function testAttributes()
    {
        $metadata = new ClassMetadata(WeekDummy::class);
        $loader = new AttributeLoader();
        $this->assertTrue($loader->loadClassMetadata($metadata));

        [$aConstraint] = $metadata->properties['a']->getConstraints();
        $this->assertNull($aConstraint->min);
        $this->assertNull($aConstraint->max);

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        $this->assertSame('2010-W01', $bConstraint->min);
        $this->assertSame('2010-W02', $bConstraint->max);
    }
}

class WeekDummy
{
    #[Week]
    private string $a;

    #[Week(min: '2010-W01', max: '2010-W02')]
    private string $b;
}
