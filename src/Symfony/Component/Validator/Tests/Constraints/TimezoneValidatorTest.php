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

use Symfony\Component\Validator\Constraints\Timezone;
use Symfony\Component\Validator\Constraints\TimezoneValidator;
use Symfony\Component\Validator\Validation;

/**
 * @author Mickaël Andrieu <andrieu.travail@gmail.com>
 */
class TimezoneValidatorTest extends AbstractConstraintValidatorTest
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    protected function createValidator()
    {
        return new TimezoneValidator();
    }

    public function testEuropeParisIsValid()
    {
        $this->validator->validate('Europe/Paris', new Timezone());

        $this->assertNoViolation();
    }

    public function testAmericaIndianaIndianapolisIsValid()
    {
        $this->validator->validate('America/Indiana/Indianapolis', new Timezone());

        $this->assertNoViolation();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Timezone());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Timezone());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $valueAsString)
    {
        $constraint = new Timezone(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $valueAsString)
            ->assertRaised();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Timezone());
    }

    public function getInvalidValues()
    {
        return array(
            array('foobar', '"foobar"'),
            array(0, '0'),
            array(false, 'false'),
            array(1234, '1234'),
        );
    }
}
