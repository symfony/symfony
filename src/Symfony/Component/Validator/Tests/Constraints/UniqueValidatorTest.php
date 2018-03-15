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

use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Constraints\UniqueValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class UniqueValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new UniqueValidator();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsUniqueConstraintCompatibleType()
    {
        $this->validator->validate('', new Unique());
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues($value)
    {
        $this->validator->validate($value, new Unique());

        $this->assertNoViolation();
    }

    public function getValidValues()
    {
        return array(
            yield 'null' => array(array(null)),
            yield 'empty array' => array(array()),
            yield 'single integer' => array(array(5)),
            yield 'single string' => array(array('a')),
            yield 'single object' => array(array(new \stdClass())),
            yield 'unique booleans' => array(array(true, false)),
            yield 'unique integers' => array(array(1, 2, 3, 4, 5, 6)),
            yield 'unique floats' => array(array(0.1, 0.2, 0.3)),
            yield 'unique strings' => array(array('a', 'b', 'c')),
            yield 'unique arrays' => array(array(array(1, 2), array(2, 4), array(4, 6))),
            yield 'unique objects' => array(array(new \stdClass(), new \stdClass())),
        );
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Unique(array(
            'message' => 'myMessage',
        ));
        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
             ->setParameter('{{ value }}', 'array')
             ->setCode(Unique::IS_NOT_UNIQUE)
             ->assertRaised();
    }

    public function getInvalidValues()
    {
        $object = new \stdClass();

        return array(
            yield 'not unique booleans' => array(array(true, true)),
            yield 'not unique integers' => array(array(1, 2, 3, 3)),
            yield 'not unique floats' => array(array(0.1, 0.2, 0.1)),
            yield 'not unique string' => array(array('a', 'b', 'a')),
            yield 'not unique arrays' => array(array(array(1, 1), array(2, 3), array(1, 1))),
            yield 'not unique objects' => array(array($object, $object)),
        );
    }
}
