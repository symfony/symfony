<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\Min;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\CollectionValidator;

class CollectionValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $walker;
    protected $context;

    protected function setUp()
    {
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');

        $this->context = new ExecutionContext('Root', $this->walker, $metadataFactory);

        $this->validator = new CollectionValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->validator = null;
        $this->walker = null;
        $this->context = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Collection(array('fields' => array(
            'foo' => new Min(4),
        )))));
    }

    public function testFieldsAsDefaultOption()
    {
        $this->validator->isValid(array('foo' => 'foobar'), new Collection(array(
            'foo' => new Min(4),
        )));
    }

    public function testThrowsExceptionIfNotTraversable()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid('foobar', new Collection(array('fields' => array(
            'foo' => new Min(4),
        ))));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkSingleConstraint($array)
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('foo');

        $constraint = new Min(4);

        foreach ($array as $key => $value) {
            $this->walker->expects($this->once())
                                     ->method('walkConstraint')
                                     ->with($this->equalTo($constraint), $this->equalTo($value), $this->equalTo('MyGroup'), $this->equalTo('foo['.$key.']'));
        }

        $this->assertTrue($this->validator->isValid($array, new Collection(array(
            'fields' => array(
                'foo' => $constraint,
            ),
        ))));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkMultipleConstraints($array)
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('foo');

        $constraint = new Min(4);
        // can only test for twice the same constraint because PHPUnits mocking
        // can't test method calls with different arguments
        $constraints = array($constraint, $constraint);

        foreach ($array as $key => $value) {
            $this->walker->expects($this->exactly(2))
                                     ->method('walkConstraint')
                                     ->with($this->equalTo($constraint), $this->equalTo($value), $this->equalTo('MyGroup'), $this->equalTo('foo['.$key.']'));
        }

        $this->assertTrue($this->validator->isValid($array, new Collection(array(
            'fields' => array(
                'foo' => $constraints,
            )
        ))));
    }

    public function testExtraFieldsDisallowed()
    {
        $array = array(
            'foo' => 5,
            'bar' => 6,
        );

        $this->assertFalse($this->validator->isValid($array, new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
        ))));
    }

    // bug fix
    public function testNullNotConsideredExtraField()
    {
        $array = array(
            'foo' => null,
        );

        $this->assertTrue($this->validator->isValid($array, new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
        ))));
    }

    public function testExtraFieldsAllowed()
    {
        $array = array(
            'foo' => 5,
            'bar' => 6,
        );

        $this->assertTrue($this->validator->isValid($array, new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'allowExtraFields' => true,
        ))));
    }

    public function testMissingFieldsDisallowed()
    {
        $this->assertFalse($this->validator->isValid(array(), new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
        ))));
    }

    public function testMissingFieldsAllowed()
    {
        $this->assertTrue($this->validator->isValid(array(), new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'allowMissingFields' => true,
        ))));
    }

    public function getValidArguments()
    {
        return array(
            // can only test for one entry, because PHPUnits mocking does not allow
            // to expect multiple method calls with different arguments
            array(array('foo' => 3)),
            array(new \ArrayObject(array('foo' => 3))),
        );
    }

    public function testObjectShouldBeLeftUnchanged()
    {
        $value = new \ArrayObject(array(
            'foo' => 3
        ));
        $this->validator->isValid($value, new Collection(array(
            'fields' => array(
                'foo' => new Min(2),
            )
        )));

        $this->assertEquals(array(
            'foo' => 3
        ), (array) $value);
    }
}
