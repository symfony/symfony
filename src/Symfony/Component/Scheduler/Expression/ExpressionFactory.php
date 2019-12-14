<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Expression;

use Symfony\Component\Scheduler\Exception\InvalidExpressionException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExpressionFactory
{
    private const ALLOWED_MACROS = [
        '@annually' => '0 0 1 1 *',
        '@yearly'   => '0 0 1 1 *',
        '@daily'    => '0 0 * * *',
        '@weekly'   => '0 0 * * 0',
        '@monthly'  => '0 0 1 * *',
        '@reboot'   => 'reboot',
    ];

    private $expression = '* * * * *';

    public function setExpression(string $expression): void
    {
        if (0 === strpos($expression, '@')) {
            $this->setMacro($expression);

            return;
        }

        $this->expression = $expression;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function everySpecificMinutes(string $minutes): string
    {
        return $this->changeExpression(0, $minutes);
    }

    public function everySpecificHours(string $hours): string
    {
        return $this->changeExpression(1, $hours);
    }

    public function everySpecificDays(string $days): string
    {
        return $this->changeExpression(2, $days);
    }

    public function everySpecificDaysOfWeek(string $days): string
    {
        return $this->changeExpression(4, $days);
    }

    public function everySpecificMonths(string $months): string
    {
        return $this->changeExpression(3, $months);
    }

    public function every5Minutes(): string
    {
        return $this->changeExpression(0, '*/5');
    }

    public function every10Minutes(): string
    {
        return $this->changeExpression(0, '*/10');
    }

    public function every15Minutes(): string
    {
        return $this->changeExpression(0, '*/15');
    }

    public function every20Minutes(): string
    {
        return $this->changeExpression(0, '*/20');
    }

    public function every25Minutes(): string
    {
        return $this->changeExpression(0, '*/25');
    }

    public function every30Minutes(): string
    {
        return $this->changeExpression(0, '*/30');
    }

    public function everyHours(): string
    {
        return $this->changeExpression(0, '0');
    }

    public function everyDays(): string
    {
        $this->changeExpression(0, '0');
        $this->changeExpression(1, '0');

        return $this->expression;
    }

    public function everyWeeks(): string
    {
        $this->changeExpression(0, '0');
        $this->changeExpression(1, '0');
        $this->changeExpression(4, '0');

        return $this->expression;
    }

    public function everyMonths(): string
    {
        $this->changeExpression(0, '0');
        $this->changeExpression(1, '0');
        $this->changeExpression(2, '1');

        return $this->expression;
    }

    public function everyYears(): string
    {
        $this->changeExpression(0, '0');
        $this->changeExpression(1, '0');
        $this->changeExpression(2, '1');
        $this->changeExpression(3, '1');

        return $this->expression;
    }

    public function at(string $time): string
    {
        $fields = explode(':', $time);

        $this->changeExpression(0, 2 === \count($fields) ? $fields[1] : '0');
        $this->changeExpression(1, $fields[0]);

        return $this->expression;
    }

    public function setMacro(string $macro): string
    {
        if (!\array_key_exists($macro, self::ALLOWED_MACROS)) {
            throw new InvalidExpressionException(sprintf('The desired macro "%s" is not supported!', $macro));
        }

        $this->expression = $macro;

        return $this->expression;
    }

    /**
     * @param int    $position A valid position (refer to cron syntax if needed)
     * @param string $value    A valid value (typed to string to prevent type changes)
     *
     * @return string The updated expression
     */
    private function changeExpression(int $position, string $value): string
    {
        $fields = explode(' ', $this->expression);

        $fields[$position] = $value;

        return $this->expression = implode(' ', $fields);
    }
}
