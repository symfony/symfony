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

use Symfony\Component\Validator\Constraints\Base64;
use Symfony\Component\Validator\Constraints\Base64Validator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class Base64ValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): Base64Validator
    {
        return new Base64Validator();
    }

    /**
     * @dataProvider provideValidValues
     */
    public function testValidBase64(string|\Stringable $value)
    {
        $this->validator->validate($value, new Base64());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideUrlEncodedValidValues
     */
    public function testUrlEncodedValidBase64(string|\Stringable $value)
    {
        $this->validator->validate($value, new Base64(urlEncoded: true));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideInvalidValues
     */
    public function testInvalidValues(string $value)
    {
        $this->validator->validate($value, new Base64());

        $this->buildViolation('The given string is not a valid Base64 encoded string.')
            ->setCode(Base64::INVALID_STRING_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider provideInvalidTypes
     */
    public function testNonStringValues(mixed $value)
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Expected argument of type "string", ".*" given/');

        $this->validator->validate($value, new Base64());
    }

    public function testItRequiresDataUri()
    {
        $this->validator->validate('data:image/png;base64,dGVzdA==', new Base64(true));

        $this->assertNoViolation();
    }

    public function testItRequiresDataUriButNoneIsGiven()
    {
        $this->validator->validate('dGVzdA==', new Base64(true));

        $this->buildViolation('The given string is missing a data URI.')
            ->setCode(Base64::MISSING_DATA_URI_ERROR)
            ->assertRaised();
    }

    public function testItDoesntRequireDataUriButOneIsGiven()
    {
        $this->validator->validate('data:image/png;base64,dGVzdA==', new Base64());

        $this->buildViolation('The given string is not a valid Base64 encoded string.')
            ->setCode(Base64::INVALID_STRING_ERROR)
            ->assertRaised();
    }

    public static function provideValidValues()
    {
        yield [''];
        yield ['dGVzdA=='];
        yield ['dGVzdA'];
        yield [new class() implements \Stringable {
            public function __toString(): string
            {
                return 'dGVzdA==';
            }
        }];
    }

    public static function provideUrlEncodedValidValues()
    {
        yield ['dGVzd%2B%2FA%3D%3D'];
        yield ['dGVzdA%3D%3D'];
        yield ['dGVzdA'];
        yield [new class() implements \Stringable {
            public function __toString(): string
            {
                return 'dGVzdA%3D%3D';
            }
        }];
    }

    public static function provideInvalidValues()
    {
        yield 'missing equal sign' => ['dGVzdA='];
        yield 'illegal chars' => ['é"'];
        yield 'emoji' => ['😊'];
        yield 'url encoded' => ['dGVzdA%3D%3D'];
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
