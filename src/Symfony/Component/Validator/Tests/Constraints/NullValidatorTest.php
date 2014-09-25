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

use Symfony\Component\Validator\Constraints\Null;
use Symfony\Component\Validator\Constraints\NullValidator;
use Symfony\Component\Validator\Validation;

class NullValidatorTest extends AbstractConstraintValidatorTest
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    protected function createValidator()
    {
        return new NullValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Null());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $valueAsString)
    {
        $constraint = new Null(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $valueAsString)
            ->assertRaised();
    }

    public function getInvalidValues()
    {
        return array(
            array(0, '0'),
            array(false, 'false'),
            array(true, 'true'),
            array('', '""'),
            array('foo bar', '"foo bar"'),
            array(new \DateTime(), 'object'),
            array(new \stdClass(), 'object'),
            array(array(), 'array'),
        );
    }
}
