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

use Symfony\Component\Validator\Constraints\Charset;
use Symfony\Component\Validator\Constraints\CharsetValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\StringableValue;

class CharsetValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): CharsetValidator
    {
        return new CharsetValidator();
    }

    /**
     * @dataProvider provideValidValues
     */
    public function testEncodingIsValid(string|\Stringable $value, array|string $encodings)
    {
        $this->validator->validate($value, new Charset(encodings: $encodings));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideInvalidValues
     */
    public function testInvalidValues(string $value, array|string $encodings)
    {
        $this->validator->validate($value, new Charset(encodings: $encodings));

        $this->buildViolation('The detected character encoding is invalid ({{ detected }}). Allowed encodings are {{ encodings }}.')
            ->setParameter('{{ detected }}', 'UTF-8')
            ->setParameter('{{ encodings }}', implode(', ', (array) $encodings))
            ->setCode(Charset::BAD_ENCODING_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider provideInvalidTypes
     */
    public function testNonStringValues(mixed $value)
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Expected argument of type "string", ".*" given/');

        $this->validator->validate($value, new Charset(encodings: ['UTF-8']));
    }

    public static function provideValidValues()
    {
        yield ['my ascii string', ['ASCII']];
        yield ['my ascii string', ['UTF-8']];
        yield ['my ascii string', 'UTF-8'];
        yield ['my ascii string', ['ASCII', 'UTF-8']];
        yield ['my ûtf 8', ['ASCII', 'UTF-8']];
        yield ['my ûtf 8', ['UTF-8']];
        yield ['string', ['ISO-8859-1']];
        yield [new StringableValue('my ûtf 8'), ['UTF-8']];
    }

    public static function provideInvalidValues()
    {
        yield ['my non-Äscîi string', 'ASCII'];
        yield ['my non-Äscîi string', ['ASCII']];
        yield ['😊', ['7bit']];
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
