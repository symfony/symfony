<?php

namespace Symfony\Tests\Component\Validator;

require_once __DIR__.'/../Fixtures/Entity.php';

use Symfony\Component\Validator\ValidationContext;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Constraints\ValidValidator;
use Symfony\Tests\Component\Validator\Fixtures\Entity;

class ValidValidatorTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Tests\Component\Validator\Fixtures\Entity';

    protected $validator;
    protected $factory;
    protected $walker;
    protected $context;

    public function setUp()
    {
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $this->factory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $messageInterpolator = $this->getMock('Symfony\Component\Validator\MessageInterpolator\MessageInterpolatorInterface');

        $this->context = new ValidationContext('Root', $this->walker, $this->factory, $messageInterpolator);

        $this->validator = new ValidValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Valid()));
    }

    public function testThrowsExceptionIfNotObjectOrArray()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid('foobar', new Valid());
    }

    public function testWalkObject()
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('foo');

        $metadata = $this->createClassMetadata();
        $entity = new Entity();

        $this->factory->expects($this->once())
                                    ->method('getClassMetadata')
                                    ->with($this->equalTo(self::CLASSNAME))
                                    ->will($this->returnValue($metadata));

        $this->walker->expects($this->once())
                                 ->method('walkClass')
                                 ->with($this->equalTo($metadata), $this->equalTo($entity), 'MyGroup', 'foo');

        $this->assertTrue($this->validator->isValid($entity, new Valid()));
    }

    public function testWalkArray()
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('foo');

        $constraint = new Valid();
        $entity = new Entity();
        // can only test for one object due to PHPUnit's mocking limitations
        $array = array('key' => $entity);

        $this->walker->expects($this->once())
                                 ->method('walkConstraint')
                                 ->with($this->equalTo($constraint), $this->equalTo($entity), 'MyGroup', 'foo[key]');

        $this->assertTrue($this->validator->isValid($array, $constraint));
    }

    public function testValidateClass_Succeeds()
    {
        $metadata = $this->createClassMetadata();
        $entity = new Entity();

        $this->factory->expects($this->any())
                                    ->method('getClassMetadata')
                                    ->with($this->equalTo(self::CLASSNAME))
                                    ->will($this->returnValue($metadata));

        $this->assertTrue($this->validator->isValid($entity, new Valid(array('class' => self::CLASSNAME))));
    }

    public function testValidateClass_Fails()
    {
        $entity = new \stdClass();

        $this->assertFalse($this->validator->isValid($entity, new Valid(array('class' => self::CLASSNAME))));
    }

    protected function createClassMetadata()
    {
        return $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadata', array(), array(), '', false);
    }
}