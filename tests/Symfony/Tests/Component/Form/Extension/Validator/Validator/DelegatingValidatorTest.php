<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Validator\Validator;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Extension\Validator\Validator\DelegatingValidator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ExecutionContext;

class DelegatingValidatorTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $factory;

    private $builder;

    private $delegate;

    private $validator;

    private $message;

    private $params;

    protected function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->delegate = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $this->validator = new DelegatingValidator($this->delegate);
        $this->message = 'Message';
        $this->params = array('foo' => 'bar');
    }

    protected function getMockGraphWalker()
    {
        return $this->getMockBuilder('Symfony\Component\Validator\GraphWalker')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function getMockMetadataFactory()
    {
        return $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
    }

    protected function getMockTransformer()
    {
        return $this->getMock('Symfony\Component\Form\DataTransformerInterface', array(), array(), '', false, false);
    }

    protected function getConstraintViolation($propertyPath)
    {
        return new ConstraintViolation($this->message, $this->params, null, $propertyPath, null);
    }

    protected function getFormError()
    {
        return new FormError($this->message, $this->params);
    }

    protected function getBuilder($name = 'name', $propertyPath = null)
    {
        $builder = new FormBuilder($name, $this->factory, $this->dispatcher);
        $builder->setAttribute('property_path', new PropertyPath($propertyPath ?: $name));
        $builder->setAttribute('error_mapping', array());

        return $builder;
    }

    protected function getForm($name = 'name', $propertyPath = null)
    {
        return $this->getBuilder($name, $propertyPath)->getForm();
    }

    protected function getMockForm()
    {
        return $this->getMock('Symfony\Tests\Component\Form\FormInterface');
    }

    public function testUseValidateValueWhenValidationConstraintExist()
    {
        $constraint = $this->getMockForAbstractClass('Symfony\Component\Validator\Constraint');
        $form = $this
            ->getBuilder('name')
            ->setAttribute('validation_constraint', $constraint)
            ->getForm();

        $this->delegate->expects($this->once())->method('validateValue');

        $this->validator->validate($form);
    }

    public function testFormErrorsOnForm()
    {
        $form = $this->getForm();

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('constrainedProp')
            )));

        $this->validator->validate($form);

        $this->assertEquals(array($this->getFormError()), $form->getErrors());
    }

    public function testFormErrorsOnChild()
    {
        $parent = $this->getForm();
        $child = $this->getForm('firstName');

        $parent->add($child);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('children.data.firstName')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertEquals(array($this->getFormError()), $child->getErrors());
    }

    public function testFormErrorsOnChildLongPropertyPath()
    {
        $parent = $this->getForm();
        $child = $this->getForm('street', 'address.street');

        $parent->add($child);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('children[address].data.street.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertEquals(array($this->getFormError()), $child->getErrors());
    }

    public function testFormErrorsOnGrandChild()
    {
        $parent = $this->getForm();
        $child = $this->getForm('address');
        $grandChild = $this->getForm('street');

        $parent->add($child);
        $child->add($grandChild);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('children[address].data.street')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertFalse($child->hasErrors());
        $this->assertEquals(array($this->getFormError()), $grandChild->getErrors());
    }

    public function testFormErrorsOnChildWithChildren()
    {
        $parent = $this->getForm();
        $child = $this->getForm('address');
        $grandChild = $this->getForm('street');

        $parent->add($child);
        $child->add($grandChild);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('children[address].constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertEquals(array($this->getFormError()), $child->getErrors());
        $this->assertFalse($grandChild->hasErrors());
    }

    public function testFormErrorsOnParentIfNoChildFound()
    {
        $parent = $this->getForm();
        $child = $this->getForm('firstName');

        $parent->add($child);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('children[lastName].constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertEquals(array($this->getFormError()), $parent->getErrors());
        $this->assertFalse($child->hasErrors());
    }

    public function testFormErrorsOnCollectionForm()
    {
        $parent = $this->getForm();

        for ($i = 0; $i < 2; $i++) {
            $child = $this->getForm((string)$i, '['.$i.']');
            $child->add($this->getForm('firstName'));
            $parent->add($child);
        }

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('children[0].data.firstName'),
                $this->getConstraintViolation('children[1].data.firstName'),
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());

        foreach ($parent as $child) {
            $grandChild = $child->get('firstName');

            $this->assertFalse($child->hasErrors());
            $this->assertTrue($grandChild->hasErrors());
            $this->assertEquals(array($this->getFormError()), $grandChild->getErrors());
        }
    }

    public function testDataErrorsOnForm()
    {
        $form = $this->getForm();

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.constrainedProp')
            )));

        $this->validator->validate($form);

        $this->assertEquals(array($this->getFormError()), $form->getErrors());
    }

    public function testDataErrorsOnChild()
    {
        $parent = $this->getForm();
        $child = $this->getForm('firstName');

        $parent->add($child);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.firstName.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertEquals(array($this->getFormError()), $child->getErrors());
    }

    public function testDataErrorsOnChildLongPropertyPath()
    {
        $parent = $this->getForm();
        $child = $this->getForm('street', 'address.street');

        $parent->add($child);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.address.street.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertEquals(array($this->getFormError()), $child->getErrors());
    }

    public function testDataErrorsOnChildWithChildren()
    {
        $parent = $this->getForm();
        $child = $this->getForm('address');
        $grandChild = $this->getForm('street');

        $parent->add($child);
        $child->add($grandChild);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.address.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertEquals(array($this->getFormError()), $child->getErrors());
        $this->assertFalse($grandChild->hasErrors());
    }

    public function testDataErrorsOnGrandChild()
    {
        $parent = $this->getForm();
        $child = $this->getForm('address');
        $grandChild = $this->getForm('street');

        $parent->add($child);
        $child->add($grandChild);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.address.street.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertFalse($child->hasErrors());
        $this->assertEquals(array($this->getFormError()), $grandChild->getErrors());
    }

    public function testDataErrorsOnGrandChild2()
    {
        $parent = $this->getForm();
        $child = $this->getForm('address');
        $grandChild = $this->getForm('street');

        $parent->add($child);
        $child->add($grandChild);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('children[address].data.street.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertFalse($child->hasErrors());
        $this->assertEquals(array($this->getFormError()), $grandChild->getErrors());
    }

    public function testDataErrorsOnGrandChild3()
    {
        $parent = $this->getForm();
        $child = $this->getForm('address');
        $grandChild = $this->getForm('street');

        $parent->add($child);
        $child->add($grandChild);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data[address].street.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertFalse($child->hasErrors());
        $this->assertEquals(array($this->getFormError()), $grandChild->getErrors());
    }

    public function testDataErrorsOnParentIfNoChildFound()
    {
        $parent = $this->getForm();
        $child = $this->getForm('firstName');

        $parent->add($child);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.lastName.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertEquals(array($this->getFormError()), $parent->getErrors());
        $this->assertFalse($child->hasErrors());
    }

    public function testDataErrorsOnCollectionForm()
    {
        $parent = $this->getForm();
        $child = $this->getForm('addresses');

        $parent->add($child);

        for ($i = 0; $i < 2; $i++) {
            $collection = $this->getForm((string)$i, '['.$i.']');
            $collection->add($this->getForm('street'));

            $child->add($collection);
        }

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data[0].street'),
                $this->getConstraintViolation('data.addresses[1].street')
            )));

        $child->setData(array());

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors(), '->hasErrors() returns false for parent form');
        $this->assertFalse($child->hasErrors(), '->hasErrors() returns false for child form');

        foreach ($child as $collection) {
            $grandChild = $collection->get('street');

            $this->assertFalse($collection->hasErrors());
            $this->assertTrue($grandChild->hasErrors());
            $this->assertEquals(array($this->getFormError()), $grandChild->getErrors());
        }
    }

    public function testMappedError()
    {
        $parent = $this->getBuilder()
            ->setAttribute('error_mapping', array(
                'passwordPlain' => 'password',
            ))
            ->getForm();
        $child = $this->getForm('password');

        $parent->add($child);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.passwordPlain.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertEquals(array($this->getFormError()), $child->getErrors());
    }

    public function testMappedNestedError()
    {
        $parent = $this->getBuilder()
            ->setAttribute('error_mapping', array(
                'address.streetName' => 'address.street',
            ))
            ->getForm();
        $child = $this->getForm('address');
        $grandChild = $this->getForm('street');

        $parent->add($child);
        $child->add($grandChild);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.address.streetName.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertFalse($child->hasErrors());
        $this->assertEquals(array($this->getFormError()), $grandChild->getErrors());
    }

    public function testNestedMappingUsingForm()
    {
        $parent = $this->getForm();
        $child = $this->getBuilder('address')
            ->setAttribute('error_mapping', array(
                'streetName' => 'street',
            ))
            ->getForm();
        $grandChild = $this->getForm('street');

        $parent->add($child);
        $child->add($grandChild);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('children[address].data.streetName.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertFalse($child->hasErrors());
        $this->assertEquals(array($this->getFormError()), $grandChild->getErrors());
    }

    public function testNestedMappingUsingData()
    {
        $parent = $this->getForm();
        $child = $this->getBuilder('address')
            ->setAttribute('error_mapping', array(
                'streetName' => 'street',
            ))
            ->getForm();
        $grandChild = $this->getForm('street');

        $parent->add($child);
        $child->add($grandChild);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.address.streetName.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertFalse($child->hasErrors());
        $this->assertEquals(array($this->getFormError()), $grandChild->getErrors());
    }

    public function testNestedMappingVirtualForm()
    {
        $parent = $this->getBuilder()
            ->setAttribute('error_mapping', array(
                'streetName' => 'street',
            ))
            ->getForm();
        $child = $this->getBuilder('address')
            ->setAttribute('virtual', true)
            ->getForm();
        $grandChild = $this->getForm('street');

        $parent->add($child);
        $child->add($grandChild);

        $this->delegate->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array(
                $this->getConstraintViolation('data.streetName.constrainedProp')
            )));

        $this->validator->validate($parent);

        $this->assertFalse($parent->hasErrors());
        $this->assertFalse($child->hasErrors());
        $this->assertEquals(array($this->getFormError()), $grandChild->getErrors());
    }

    public function testValidateFormData()
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $context = new ExecutionContext('Root', $graphWalker, $metadataFactory);
        $object = $this->getMock('\stdClass');
        $form = $this->getBuilder()
            ->setAttribute('validation_groups', array('group1', 'group2'))
            ->getForm();

        $graphWalker->expects($this->at(0))
            ->method('walkReference')
            ->with($object, 'group1', 'data', true);
        $graphWalker->expects($this->at(1))
            ->method('walkReference')
            ->with($object, 'group2', 'data', true);

        $form->setData($object);

        DelegatingValidator::validateFormData($form, $context);
    }

    public function testValidateFormDataUsesInheritedValidationGroup()
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $context = new ExecutionContext('Root', $graphWalker, $metadataFactory);
        $context->setPropertyPath('path');
        $object = $this->getMock('\stdClass');

        $parent = $this->getBuilder()
            ->setAttribute('validation_groups', 'group')
            ->getForm();
        $child = $this->getBuilder()
            ->setAttribute('validation_groups', null)
            ->getForm();
        $parent->add($child);

        $child->setData($object);

        $graphWalker->expects($this->once())
            ->method('walkReference')
            ->with($object, 'group', 'path.data', true);

        DelegatingValidator::validateFormData($child, $context);
    }

    public function testValidateFormDataAppendsPropertyPath()
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $context = new ExecutionContext('Root', $graphWalker, $metadataFactory);
        $context->setPropertyPath('path');
        $object = $this->getMock('\stdClass');
        $form = $this->getForm();

        $graphWalker->expects($this->once())
            ->method('walkReference')
            ->with($object, 'Default', 'path.data', true);

        $form->setData($object);

        DelegatingValidator::validateFormData($form, $context);
    }

    public function testValidateFormDataSetsCurrentPropertyToData()
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $context = new ExecutionContext('Root', $graphWalker, $metadataFactory);
        $object = $this->getMock('\stdClass');
        $form = $this->getForm();
        $test = $this;

        $graphWalker->expects($this->once())
            ->method('walkReference')
            ->will($this->returnCallback(function () use ($context, $test) {
                $test->assertEquals('data', $context->getCurrentProperty());
            }));

        $form->setData($object);

        DelegatingValidator::validateFormData($form, $context);
    }

    public function testValidateFormDataDoesNotWalkScalars()
    {
        $graphWalker = $this->getMockGraphWalker();
        $metadataFactory = $this->getMockMetadataFactory();
        $context = new ExecutionContext('Root', $graphWalker, $metadataFactory);
        $clientTransformer = $this->getMockTransformer();

        $form = $this->getBuilder()
            ->appendClientTransformer($clientTransformer)
            ->getForm();

        $graphWalker->expects($this->never())
            ->method('walkReference');

        $clientTransformer->expects($this->atLeastOnce())
            ->method('reverseTransform')
            ->will($this->returnValue('foobar'));

        $form->bind(array('foo' => 'bar')); // reverse transformed to "foobar"

        DelegatingValidator::validateFormData($form, $context);
    }

    public function testValidateIgnoresNonRoot()
    {
        $form = $this->getMockForm();
        $form->expects($this->once())
            ->method('isRoot')
            ->will($this->returnValue(false));

        $this->delegate->expects($this->never())
            ->method('validate');

        $this->validator->validate($form);
    }
}
