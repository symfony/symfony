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

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotBlankValidator;
use Symfony\Component\Validator\Validation;

class NotBlankValidatorTest extends AbstractConstraintValidatorTest
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    protected function createValidator()
    {
        return new NotBlankValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->validator->validate($value, new NotBlank());

        $this->assertNoViolation();
    }

    public function getValidValues()
    {
        return array(
            array('foobar'),
            array(0),
            array(0.0),
            array('0'),
            array(1234),
        );
    }

    public function testNullIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate(null, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'null')
            ->assertRaised();
    }

    public function testBlankIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate('', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '""')
            ->assertRaised();
    }

    public function testFalseIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate(false, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'false')
            ->assertRaised();
    }

    public function testEmptyArrayIsInvalid()
    {
        $constraint = new NotBlank(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate(array(), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'array')
            ->assertRaised();
    }
}
