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

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\Min;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Collection\Required;
use Symfony\Component\Validator\Constraints\Collection\Optional;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\CollectionValidator;

abstract class CollectionValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $walker;
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new CollectionValidator();
        $this->validator->initialize($this->context);

        $this->context->expects($this->any())
            ->method('getGraphWalker')
            ->will($this->returnValue($this->walker));
        $this->context->expects($this->any())
            ->method('getGroup')
            ->will($this->returnValue('MyGroup'));
        $this->context->expects($this->any())
            ->method('getPropertyPath')
            ->will($this->returnValue('foo.bar'));
    }

    protected function tearDown()
    {
        $this->walker = null;
        $this->context = null;
        $this->validator = null;
    }

    abstract protected function prepareTestData(array $contents);

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $this->validator->validate(null, new Collection(array('fields' => array(
            'foo' => new Min(4),
        ))));
    }

    public function testFieldsAsDefaultOption()
    {
        $data = $this->prepareTestData(array('foo' => 'foobar'));

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $this->validator->validate($data, new Collection(array(
            'foo' => new Min(4),
        )));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        $this->validator->validate('foobar', new Collection(array('fields' => array(
            'foo' => new Min(4),
        ))));
    }

    public function testWalkSingleConstraint()
    {
        $constraint = new Min(4);

        $array = array(
            'foo' => 3,
            'bar' => 5,
        );
        $i = 0;

        foreach ($array as $key => $value) {
            $this->walker->expects($this->at($i++))
                ->method('walkConstraint')
                ->with($constraint, $value, 'MyGroup', 'foo.bar['.$key.']');
        }

        $data = $this->prepareTestData($array);

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $this->validator->validate($data, new Collection(array(
            'fields' => array(
                'foo' => $constraint,
                'bar' => $constraint,
            ),
        )));
    }

    public function testWalkMultipleConstraints()
    {
        $constraints = array(
            new Min(4),
            new NotNull(),
        );

        $array = array(
            'foo' => 3,
            'bar' => 5,
        );
        $i = 0;

        foreach ($array as $key => $value) {
            foreach ($constraints as $constraint) {
                $this->walker->expects($this->at($i++))
                    ->method('walkConstraint')
                    ->with($constraint, $value, 'MyGroup', 'foo.bar['.$key.']');
            }
        }

        $data = $this->prepareTestData($array);

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $this->validator->validate($data, new Collection(array(
            'fields' => array(
                'foo' => $constraints,
                'bar' => $constraints,
            )
        )));
    }

    public function testExtraFieldsDisallowed()
    {
        $data = $this->prepareTestData(array(
            'foo' => 5,
            'baz' => 6,
        ));

        $this->context->expects($this->once())
            ->method('addViolationAtSubPath')
            ->with('[baz]', 'myMessage', array(
                '{{ field }}' => 'baz'
            ));

        $this->validator->validate($data, new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'extraFieldsMessage' => 'myMessage',
        )));
    }

    // bug fix
    public function testNullNotConsideredExtraField()
    {
        $data = $this->prepareTestData(array(
            'foo' => null,
        ));

        $constraint = new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
        ));

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $this->validator->validate($data, $constraint);
    }

    public function testExtraFieldsAllowed()
    {
        $data = $this->prepareTestData(array(
            'foo' => 5,
            'bar' => 6,
        ));

        $constraint = new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'allowExtraFields' => true,
        ));

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $this->validator->validate($data, $constraint);
    }

    public function testMissingFieldsDisallowed()
    {
        $data = $this->prepareTestData(array());

        $constraint = new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'missingFieldsMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolationAtSubPath')
            ->with('[foo]', 'myMessage', array(
                '{{ field }}' => 'foo',
            ));

        $this->validator->validate($data, $constraint);
    }

    public function testMissingFieldsAllowed()
    {
        $data = $this->prepareTestData(array());

        $constraint = new Collection(array(
            'fields' => array(
                'foo' => new Min(4),
            ),
            'allowMissingFields' => true,
        ));

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $this->validator->validate($data, $constraint);
    }

    public function testOptionalFieldPresent()
    {
        $data = $this->prepareTestData(array(
            'foo' => null,
        ));

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $this->validator->validate($data, new Collection(array(
            'foo' => new Optional(),
        )));
    }

    public function testOptionalFieldNotPresent()
    {
        $data = $this->prepareTestData(array());

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $this->validator->validate($data, new Collection(array(
            'foo' => new Optional(),
        )));
    }

    public function testOptionalFieldSingleConstraint()
    {
        $array = array(
            'foo' => 5,
        );

        $constraint = new Min(4);

        $this->walker->expects($this->once())
            ->method('walkConstraint')
            ->with($constraint, $array['foo'], 'MyGroup', 'foo.bar[foo]');

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $data = $this->prepareTestData($array);

        $this->validator->validate($data, new Collection(array(
            'foo' => new Optional($constraint),
        )));
    }

    public function testOptionalFieldMultipleConstraints()
    {
        $array = array(
            'foo' => 5,
        );

        $constraints = array(
            new NotNull(),
            new Min(4),
        );

        foreach ($constraints as $i => $constraint) {
            $this->walker->expects($this->at($i))
                ->method('walkConstraint')
                ->with($constraint, $array['foo'], 'MyGroup', 'foo.bar[foo]');
        }

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $data = $this->prepareTestData($array);

        $this->validator->validate($data, new Collection(array(
            'foo' => new Optional($constraints),
        )));
    }

    public function testRequiredFieldPresent()
    {
        $data = $this->prepareTestData(array(
            'foo' => null,
        ));

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $this->validator->validate($data, new Collection(array(
            'foo' => new Required(),
        )));
    }

    public function testRequiredFieldNotPresent()
    {
        $data = $this->prepareTestData(array());

        $this->context->expects($this->once())
            ->method('addViolationAtSubPath')
            ->with('[foo]', 'myMessage', array(
                '{{ field }}' => 'foo',
            ));

        $this->validator->validate($data, new Collection(array(
            'fields' => array(
                 'foo' => new Required(),
             ),
            'missingFieldsMessage' => 'myMessage',
        )));
    }

    public function testRequiredFieldSingleConstraint()
    {
        $array = array(
            'foo' => 5,
        );

        $constraint = new Min(4);

        $this->walker->expects($this->once())
            ->method('walkConstraint')
            ->with($constraint, $array['foo'], 'MyGroup', 'foo.bar[foo]');

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $data = $this->prepareTestData($array);

        $this->validator->validate($data, new Collection(array(
            'foo' => new Required($constraint),
        )));
    }

    public function testRequiredFieldMultipleConstraints()
    {
        $array = array(
            'foo' => 5,
        );

        $constraints = array(
            new NotNull(),
            new Min(4),
        );

        foreach ($constraints as $i => $constraint) {
            $this->walker->expects($this->at($i))
                ->method('walkConstraint')
                ->with($constraint, $array['foo'], 'MyGroup', 'foo.bar[foo]');
        }

        $this->context->expects($this->never())
            ->method('addViolationAtSubPath');

        $data = $this->prepareTestData($array);

        $this->validator->validate($array, new Collection(array(
            'foo' => new Required($constraints),
        )));
    }

    public function testObjectShouldBeLeftUnchanged()
    {
        $value = new \ArrayObject(array(
            'foo' => 3
        ));

        $this->validator->validate($value, new Collection(array(
            'fields' => array(
                'foo' => new Min(2),
            )
        )));

        $this->assertEquals(array(
            'foo' => 3
        ), (array) $value);
    }
}
