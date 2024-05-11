<?php

namespace Symfony\Component\Validator\Tests\Password;

use PHPUnit\Framework\TestCase;
use Stringable;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Password\PasswordStrengthEstimator;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\StringableValue;

class PasswordStrengthEstimatorTest extends TestCase
{
    /** @dataProvider getPasswords */
    public function testEstimateStrength(string|Stringable $password, int $expectedStrength): void
    {
        self::assertSame($expectedStrength, (new PasswordStrengthEstimator())->estimateStrength($password));
    }

    /** @return array<string, array<string, int>> */
    public function getPasswords(): iterable
    {
        yield ['How-is-this', PasswordStrength::STRENGTH_WEAK];
        yield ['Reasonable-pwd', PasswordStrength::STRENGTH_MEDIUM];
        yield ['This 1s a very g00d Pa55word! ;-)', PasswordStrength::STRENGTH_VERY_STRONG];
        yield ['pudding-smack-👌🏼-fox-😎', PasswordStrength::STRENGTH_VERY_STRONG];
        yield [new StringableValue('How-is-this'), PasswordStrength::STRENGTH_WEAK];
    }
}
