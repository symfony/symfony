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

use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\NotNullValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class NotNullValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NotNullValidator
    {
        return new NotNullValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->validator->validate($value, new NotNull());

        $this->assertNoViolation();
    }

    public static function getValidValues()
    {
        return [
            [0],
            [false],
            [true],
            [''],
        ];
    }

    public function testNullIsInvalid()
    {
        $this->validator->validate(null, new NotNull(message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'null')
            ->setCode(NotNull::IS_NULL_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testNullIsInvalidDoctrineStyle()
    {
        $this->validator->validate(null, new NotNull([
            'message' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'null')
            ->setCode(NotNull::IS_NULL_ERROR)
            ->assertRaised();
    }
}
