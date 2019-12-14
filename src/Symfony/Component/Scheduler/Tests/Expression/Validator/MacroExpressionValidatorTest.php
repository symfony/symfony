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
use Symfony\Component\Scheduler\Expression\Validator\MacroExpressionValidator;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MacroExpressionValidatorTest extends TestCase
{
    public function testMacroCannotBeValidated(): void
    {
        static::assertFalse((new MacroExpressionValidator())->isValid('test'));
    }

    /**
     * @param string $expression
     *
     * @dataProvider provideExpressions
     */
    public function testMacroCanBeValidated(string $expression): void
    {
        static::assertTrue((new MacroExpressionValidator())->isValid($expression));
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
    }
}
