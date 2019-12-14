<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Expression\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Expression\Validator\DaysOfMonthExpressionValidator;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DaysOfMonthExpressionValidatorTest extends TestCase
{
    public function testInvalidExpressionCannotBeValidated(): void
    {
        static::assertFalse((new DaysOfMonthExpressionValidator())->isValid('test'));
    }

    public function testValidExpressionCanBeValidated(): void
    {
        static::assertTrue((new DaysOfMonthExpressionValidator())->isValid('2'));
        static::assertTrue((new DaysOfMonthExpressionValidator())->isValid('23'));
    }
}
