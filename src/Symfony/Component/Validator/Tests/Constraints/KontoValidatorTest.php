<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Markus Malkusch <markus@malkusch.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Konto;
use Symfony\Component\Validator\Constraints\KontoValidator;

class KontoValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new KontoValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate(null, new Konto(array('blz' => 'blz', 'konto' => 'konto')));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())->method('addViolation');

        $entity = new \stdClass();
        $entity->blz = '';
        $entity->konto = '';

        $this->validator->validate($entity, new Konto(array('blz' => 'blz', 'konto' => 'konto')));
    }

    /**
     * @dataProvider getValidKontos
     */
    public function testValidKontos($entity)
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate($entity, new Konto(array('blz' => 'blz', 'konto' => 'konto')));
    }

    public function getValidKontos()
    {
        $cases = array();

        $case1 = new \stdClass();
        $case1->blz = '70169464';
        $case1->konto = '1112';
        $cases[] = array($case1);

        $case2 = new \stdClass();
        $case2->blz = '70169464';
        $case2->konto = '67067';
        $cases[] = array($case2);

        return $cases;
    }

    /**
     * @dataProvider getInvalidKontos
     */
    public function testInvalidKontos($entity)
    {
        $constraint = new Konto(array(
            'blz' => 'blz',
            'konto' => 'konto',
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $entity->konto,
            ));

        $this->validator->validate($entity, $constraint);
    }

    public function getInvalidKontos()
    {
        $cases = array();

        $case1 = new \stdClass();
        $case1->blz = '70169464';
        $case1->konto = '1234';
        $cases[] = array($case1);

        $case2 = new \stdClass();
        $case2->blz = '1234';
        $case2->konto = '1234';
        $cases[] = array($case2);

        return $cases;
    }
}
