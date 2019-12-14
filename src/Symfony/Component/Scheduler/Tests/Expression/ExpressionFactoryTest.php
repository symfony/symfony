<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Expression\ExpressionFactory;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExpressionFactoryTest extends TestCase
{
    public function testEverySpecificMinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everySpecificMinutes('*/3');

        static::assertSame('*/3 * * * *', $expression);
    }

    public function testEverySpecificHoursExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everySpecificHours('10');

        static::assertSame('* 10 * * *', $expression);
    }

    public function testEverySpecificDaysExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everySpecificDays('10');

        static::assertSame('* * 10 * *', $expression);
    }

    public function testEverySpecificMonthsExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everySpecificMonths('2');

        static::assertSame('* * * 2 *', $expression);
    }

    public function testEverySpecificDayOfWeekExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everySpecificDaysOfWeek('2');

        static::assertSame('* * * * 2', $expression);
    }

    public function testEvery5MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every5Minutes();

        static::assertSame('*/5 * * * *', $expression);
    }

    public function testEvery10MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every10Minutes();

        static::assertSame('*/10 * * * *', $expression);
    }

    public function testEvery15MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every15Minutes();

        static::assertSame('*/15 * * * *', $expression);
    }

    public function testEvery20MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every20Minutes();

        static::assertSame('*/20 * * * *', $expression);
    }

    public function testEvery25MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every25Minutes();

        static::assertSame('*/25 * * * *', $expression);
    }

    public function testEvery30MinutesExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->every30Minutes();

        static::assertSame('*/30 * * * *', $expression);
    }

    public function testEveryHoursExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everyHours();

        static::assertSame('0 * * * *', $expression);
    }

    public function testEveryDaysExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everyDays();

        static::assertSame('0 0 * * *', $expression);
    }

    public function testEveryWeeksExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everyWeeks();

        static::assertSame('0 0 * * 0', $expression);
    }

    public function testEveryMonthsExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everyMonths();

        static::assertSame('0 0 1 * *', $expression);
    }

    public function testEveryYearsExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->everyYears();

        static::assertSame('0 0 1 1 *', $expression);
    }

    public function testSpecificExpressionCanBeCreated(): void
    {
        $expression = (new ExpressionFactory())->at('10:20');

        static::assertSame('20 10 * * *', $expression);
    }

    public function testNewExpressionCanBePassed(): void
    {
        $factory = new ExpressionFactory();
        $factory->setExpression('*/45 * * * *');

        static::assertSame('*/45 * * * *', $factory->getExpression());
    }
}
