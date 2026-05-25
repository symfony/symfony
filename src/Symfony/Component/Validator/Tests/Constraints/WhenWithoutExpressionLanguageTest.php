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

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClassExistsMock;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\When;
use Symfony\Component\Validator\Exception\LogicException;

#[RunTestsInSeparateProcesses]
final class WhenWithoutExpressionLanguageTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClassExistsMock::register(When::class);
        ClassExistsMock::withMockedClasses([
            ExpressionLanguage::class => false,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        ClassExistsMock::withMockedClasses([]);
    }

    public function testClosureDoesNotRequireExpressionLanguage()
    {
        $when = new When(
            expression: static fn () => true,
            constraints: [new NotNull()],
        );

        self::assertInstanceOf(\Closure::class, $when->expression);
    }

    public function testStringExpressionRequiresExpressionLanguage()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "symfony/expression-language" component is required');

        new When(
            expression: 'true',
            constraints: [new NotNull()],
        );
    }
}
