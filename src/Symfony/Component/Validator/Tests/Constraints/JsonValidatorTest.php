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

use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\JsonValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class JsonValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): JsonValidator
    {
        return new JsonValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testJsonIsValid($value)
    {
        $this->validator->validate($value, new Json());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Json([
            'message' => 'myMessageTest',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessageTest')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode(Json::INVALID_JSON_ERROR)
            ->assertRaised();
    }

    public static function getValidValues()
    {
        return [
            ['{"planet":"earth", "country": "Morocco","city": "Rabat" ,"postcode" : 10160, "is_great": true,
			  "img" : null, "area": 118.5, "foo": 15 }'],
            [null],
            [''],
            ['"null"'],
            ['null'],
            ['"string"'],
            ['1'],
            ['true'],
            [1],
        ];
    }

    public static function getInvalidValues()
    {
        return [
            ['{"foo": 3 "bar": 4}'],
            ['{"foo": 3 ,"bar": 4'],
            ['{foo": 3, "bar": 4}'],
            ['foo'],
        ];
    }
}
