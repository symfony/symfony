<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Expression;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Exception\InvalidExpressionException;
use Symfony\Component\Scheduler\Expression\ExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\DaysOfMonthExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\DaysOfWeekExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\HoursExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\MacroExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\MinutesExpressionValidator;
use Symfony\Component\Scheduler\Expression\Validator\MonthsExpressionValidator;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExpressionValidatorTest extends TestCase
{
    public function testExpressionCannotBeValidated(): void
    {
        $validator = new ExpressionValidator([
            new DaysOfMonthExpressionValidator(),
            new HoursExpressionValidator(),
            new MinutesExpressionValidator(),
            new MonthsExpressionValidator(),
            new DaysOfWeekExpressionValidator(),
            new MacroExpressionValidator(),
        ]);

        static::expectException(InvalidExpressionException::class);
        static::assertFalse($validator->validate('* * * *'));
    }

    /**
     * @param string $expression
     *
     * @dataProvider provideExpressions
     */
    public function testExpressionCanBeValidated(string $expression): void
    {
        $validator = new ExpressionValidator([
            new DaysOfMonthExpressionValidator(),
            new HoursExpressionValidator(),
            new MinutesExpressionValidator(),
            new MonthsExpressionValidator(),
            new DaysOfWeekExpressionValidator(),
            new MacroExpressionValidator(),
        ]);

        static::assertTrue($validator->validate($expression));
    }

    public function provideExpressions(): \Generator
    {
        yield 'macro expression' => [
            '@yearly',
            '@hourly',
            '@annually',
            '@monthly',
            '@weekly',
            '@daily',
            '@reboot',
        ];
        yield 'default expression' => [
            '1 * * * *',
            '* 1 * * *',
            '* * 1 * *',
            '* * * 1 *',
            '* * * * 1',
            '1 2 3 * 4',
        ];
        yield 'complex expressions' => [
            '*/1 * * * *',
            '* /1 * * *',
            '* * 1-3 * *',
            '* * * 1,2 *',
            '* * * * 1',
            '1 2-4 3 * 4',
        ];
    }
}
