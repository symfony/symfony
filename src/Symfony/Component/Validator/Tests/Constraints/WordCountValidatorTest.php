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

use Symfony\Component\Validator\Constraints\WordCount;
use Symfony\Component\Validator\Constraints\WordCountValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\StringableValue;

/**
 * @requires extension intl
 */
class WordCountValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): WordCountValidator
    {
        return new WordCountValidator();
    }

    /**
     * @dataProvider provideValidValues
     */
    public function testValidWordCount(string|\Stringable|null $value, int $expectedWordCount)
    {
        $this->validator->validate($value, new WordCount(min: $expectedWordCount, max: $expectedWordCount));

        $this->assertNoViolation();
    }

    public function testTooShort()
    {
        $constraint = new WordCount(min: 4, minMessage: 'myMessage');
        $this->validator->validate('my ascii string', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ count }}', 3)
            ->setParameter('{{ min }}', 4)
            ->setPlural(4)
            ->setInvalidValue('my ascii string')
            ->assertRaised();
    }

    public function testTooLong()
    {
        $constraint = new WordCount(max: 3, maxMessage: 'myMessage');
        $this->validator->validate('my beautiful ascii string', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ count }}', 4)
            ->setParameter('{{ max }}', 3)
            ->setPlural(3)
            ->setInvalidValue('my beautiful ascii string')
            ->assertRaised();
    }

    /**
     * @dataProvider provideInvalidTypes
     */
    public function testNonStringValues(mixed $value)
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Expected argument of type "string", ".*" given/');

        $this->validator->validate($value, new WordCount(min: 1));
    }

    public static function provideValidValues()
    {
        yield ['my ascii string', 3];
        yield ["   with a\nnewline", 3];
        yield ['皆さん、こんにちは。', 4];
        yield ['你好，世界！这是一个测试。', 9];
        yield [new StringableValue('my ûtf 8'), 3];
        yield [null, 1]; // null should always pass and eventually be handled by NotNullValidator
        yield ['', 1]; // empty string should always pass and eventually be handled by NotBlankValidator
    }

    public static function provideInvalidTypes()
    {
        yield [true];
        yield [false];
        yield [1];
        yield [1.1];
        yield [[]];
        yield [new \stdClass()];
    }
}
