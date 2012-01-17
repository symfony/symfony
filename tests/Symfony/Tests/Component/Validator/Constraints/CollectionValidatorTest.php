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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\CollectionValidator;

/**
 * This class is a hand written simplified version of PHP native `ArrayObject`
 * class, to show that it behaves different than PHP native implementation.
 */
class TestArrayObject implements \ArrayAccess, \IteratorAggregate, \Countable, \Serializable
{
    private $array;

    public function __construct(array $array = null)
    {
        $this->array = (array) ($array ?: array());
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->array);
    }

    public function offsetGet($offset)
    {
        return $this->array[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if (null === $offset) {
            $this->array[] = $value;
        } else {
            $this->array[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        if (array_key_exists($offset, $this->array)) {
            unset($this->array[$offset]);
        }
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->array);
    }

    public function count()
    {
        return count($this->array);
    }

    public function serialize()
    {
        return serialize($this->array);
    }

    public function unserialize($serialized)
    {
        $this->array = (array) unserialize((string) $serialized);
    }
}

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
        $this->assertTrue($this->validator->isValid(array('foo' => 'foobar'), new Collection(array(
            'foo' => new Min(4),
        ))));
        $this->assertTrue($this->validator->isValid(new \ArrayObject(array('foo' => 'foobar')), new Collection(array(
            'foo' => new Min(4),
        ))));
        $this->assertTrue($this->validator->isValid(new TestArrayObject(array('foo' => 'foobar')), new Collection(array(
            'foo' => new Min(4),
        ))));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
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

    /**
     * @dataProvider getArgumentsWithExtraFields
     */
    public function testExtraFieldsDisallowed($array)
    {
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
        $collection = new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
        ));

        $this->assertTrue($this->validator->isValid($array, $collection));
        $this->assertTrue($this->validator->isValid(new \ArrayObject($array), $collection));
        $this->assertTrue($this->validator->isValid(new TestArrayObject($array), $collection));
    }

    public function testExtraFieldsAllowed()
    {
        $array = array(
            'foo' => 5,
            'bar' => 6,
        );
        $collection = new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'allowExtraFields' => true,
        ));

        $this->assertTrue($this->validator->isValid($array, $collection));
        $this->assertTrue($this->validator->isValid(new \ArrayObject($array), $collection));
        $this->assertTrue($this->validator->isValid(new TestArrayObject($array), $collection));
    }

    public function testMissingFieldsDisallowed()
    {
        $this->assertFalse($this->validator->isValid(array(), new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
        ))));
        $this->assertFalse($this->validator->isValid(new \ArrayObject(array()), new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
        ))));
        $this->assertFalse($this->validator->isValid(new TestArrayObject(array()), new Collection(array(
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
        $this->assertTrue($this->validator->isValid(new \ArrayObject(array()), new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'allowMissingFields' => true,
        ))));
        $this->assertTrue($this->validator->isValid(new TestArrayObject(array()), new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'allowMissingFields' => true,
        ))));
    }

    public function testArrayAccessObject() {
        $value = new TestArrayObject();
        $value['foo'] = 12;
        $value['asdf'] = 'asdfaf';

        $this->assertTrue(isset($value['asdf']));
        $this->assertTrue(isset($value['foo']));
        $this->assertFalse(empty($value['asdf']));
        $this->assertFalse(empty($value['foo']));

        $result = $this->validator->isValid($value, new Collection(array(
            'fields' => array(
                'foo' => new NotBlank(),
                'asdf' => new NotBlank()
            )
        )));

        $this->assertTrue($result);
    }

    public function testArrayObject() {
        $value = new \ArrayObject(array());
        $value['foo'] = 12;
        $value['asdf'] = 'asdfaf';

        $this->assertTrue(isset($value['asdf']));
        $this->assertTrue(isset($value['foo']));
        $this->assertFalse(empty($value['asdf']));
        $this->assertFalse(empty($value['foo']));

        $result = $this->validator->isValid($value, new Collection(array(
            'fields' => array(
                'foo' => new NotBlank(),
                'asdf' => new NotBlank()
            )
        )));

        $this->assertTrue($result);
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

    public function getValidArguments()
    {
        return array(
            // can only test for one entry, because PHPUnits mocking does not allow
            // to expect multiple method calls with different arguments
            array(array('foo' => 3)),
            array(new \ArrayObject(array('foo' => 3))),
            array(new TestArrayObject(array('foo' => 3))),
        );
    }

    public function getArgumentsWithExtraFields()
    {
        return array(
            array(array(
                'foo' => 5,
                'bar' => 6,
            )),
            array(new \ArrayObject(array(
                'foo' => 5,
                'bar' => 6,
            ))),
            array(new TestArrayObject(array(
                'foo' => 5,
                'bar' => 6,
            )))
        );
    }
}

