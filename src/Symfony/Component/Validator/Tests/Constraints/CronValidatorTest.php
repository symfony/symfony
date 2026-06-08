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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\Cron;
use Symfony\Component\Validator\Constraints\CronValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class CronValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): CronValidator
    {
        return new CronValidator();
    }

    public function testNullIsValid()
    {
        $this->validate(null, new Cron());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validate('', new Cron());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validate(new \stdClass(), new Cron());
    }

    /**
     * Standard expressions must be accepted in every mode (mode hierarchy: STANDARD ⊂ ALIASES ⊂ HASHED).
     */
    #[DataProvider('getStandardExpressionsAcrossAllModes')]
    public function testStandardExpressionsAreAcceptedInEveryMode(string $expression, string $mode)
    {
        $this->validate($expression, new Cron(mode: $mode));

        $this->assertNoViolation();
    }

    public static function getStandardExpressionsAcrossAllModes(): iterable
    {
        foreach (self::getValidStandardCronExpressions() as [$expression]) {
            yield [$expression, Cron::MODE_STANDARD];
            yield [$expression, Cron::MODE_ALIASES];
            yield [$expression, Cron::MODE_HASHED];
        }
    }

    public static function getValidStandardCronExpressions(): array
    {
        return [
            ['* * * * *'],
            ['*/5 * * * *'],
            ['0 0 * * *'],
            ['0 9-17 * * 1-5'],
            ['0 0 1 1 *'],
            ['30 6 * * 0'],
            ['0,15,30,45 * * * *'],
            ['0 0 1 JAN,JUL *'],
            ['0 9 * * MON-FRI'],
            ['15-45/15 * * * *'],
            ['0 0 * * 7'],
            ['  * * * * *  '],
            ['*	*	*	*	*'],
        ];
    }

    /**
     * @-prefixed aliases must be accepted in the aliases and hashed modes.
     */
    #[DataProvider('getAliasExpressionsAcrossPermissiveModes')]
    public function testAliasExpressionsAreAcceptedInAliasesAndHashedModes(string $expression, string $mode)
    {
        $this->validate($expression, new Cron(mode: $mode));

        $this->assertNoViolation();
    }

    public static function getAliasExpressionsAcrossPermissiveModes(): iterable
    {
        foreach (self::getValidAliasExpressions() as [$expression]) {
            yield [$expression, Cron::MODE_ALIASES];
            yield [$expression, Cron::MODE_HASHED];
        }
    }

    /**
     * @-prefixed aliases must be rejected in the strict standard mode.
     */
    #[DataProvider('getValidAliasExpressions')]
    public function testAliasExpressionsAreRejectedInStandardMode(string $expression)
    {
        $constraint = new Cron(message: 'testMessage', mode: Cron::MODE_STANDARD);

        $this->validate($expression, $constraint);

        $this->buildViolation('testMessage')
            ->setParameter('{{ value }}', '"'.$expression.'"')
            ->setCode(Cron::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getValidAliasExpressions(): array
    {
        return [
            ['@yearly'],
            ['@annually'],
            ['@monthly'],
            ['@weekly'],
            ['@daily'],
            ['@midnight'],
            ['@hourly'],
        ];
    }

    /**
     * #-prefixed hashed expressions must be accepted only in the hashed mode.
     */
    #[DataProvider('getValidHashedExpressions')]
    public function testHashedExpressionsAreAcceptedInHashedMode(string $expression)
    {
        $this->validate($expression, new Cron(mode: Cron::MODE_HASHED));

        $this->assertNoViolation();
    }

    /**
     * #-prefixed hashed expressions must be rejected in the standard and aliases modes.
     */
    #[DataProvider('getHashedExpressionsAcrossStricterModes')]
    public function testHashedExpressionsAreRejectedInStandardAndAliasesModes(string $expression, string $mode)
    {
        $constraint = new Cron(message: 'testMessage', mode: $mode);

        $this->validate($expression, $constraint);

        $this->buildViolation('testMessage')
            ->setParameter('{{ value }}', '"'.$expression.'"')
            ->setCode(Cron::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getHashedExpressionsAcrossStricterModes(): iterable
    {
        foreach (self::getValidHashedExpressions() as [$expression]) {
            yield [$expression, Cron::MODE_STANDARD];
            yield [$expression, Cron::MODE_ALIASES];
        }
    }

    public static function getValidHashedExpressions(): array
    {
        return [
            ['#hourly'],
            ['#daily'],
            ['#weekly'],
            ['#monthly'],
            ['#yearly'],
            ['#annually'],
            ['#midnight'],
            ['# # * * *'],
            ['# #(0-2) * * #'],
        ];
    }

    #[DataProvider('getInvalidCronExpressions')]
    public function testInvalidCronExpressions(string $expression)
    {
        $constraint = new Cron(message: 'testMessage');

        $this->validate($expression, $constraint);

        $this->buildViolation('testMessage')
            ->setParameter('{{ value }}', '"'.$expression.'"')
            ->setCode(Cron::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidCronExpressions(): array
    {
        return [
            ['not a cron'],
            ['* * *'],
            ['* * * * * *'],
            ['60 * * * *'],
            ['* 24 * * *'],
            ['* * 32 * *'],
            ['* * * 13 *'],
            ['* * * * 8'],
            ['*/0 * * * *'],
            ['*/ * * * *'],
            ['/5 * * * *'],
            ['1,,2 * * * *'],
            ['* * * FOO *'],
            ['@invalid'],
        ];
    }
}
