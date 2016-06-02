<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\EventListener;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Form\Extension\Validator\EventListener\ValidationListener;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class ValidationListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $dispatcher;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $factory;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $validator;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $violationMapper;

    /**
     * @var ValidationListener
     */
    private $listener;

    private $message;

    private $messageTemplate;

    private $params;

    protected function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');
        $this->violationMapper = $this->getMock('Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapperInterface');
        $this->listener = new ValidationListener($this->validator, $this->violationMapper);
        $this->message = 'Message';
        $this->messageTemplate = 'Message template';
        $this->params = array('foo' => 'bar');
    }

    private function getConstraintViolation($code = null)
    {
        return new ConstraintViolation($this->message, $this->messageTemplate, $this->params, null, 'prop.path', null, null, $code, new Form());
    }

    private function getBuilder($name = 'name', $propertyPath = null, $dataClass = null)
    {
        $builder = new FormBuilder($name, $dataClass, $this->dispatcher, $this->factory);
        $builder->setPropertyPath(new PropertyPath($propertyPath ?: $name));
        $builder->setAttribute('error_mapping', array());
        $builder->setErrorBubbling(false);
        $builder->setMapped(true);

        return $builder;
    }

    private function getForm($name = 'name', $propertyPath = null, $dataClass = null)
    {
        return $this->getBuilder($name, $propertyPath, $dataClass)->getForm();
    }

    private function getMockForm()
    {
        return $this->getMock('Symfony\Component\Form\Test\FormInterface');
    }

    // More specific mapping tests can be found in ViolationMapperTest
    public function testMapViolation()
    {
        $violation = $this->getConstraintViolation();
        $form = $this->getForm('street');

        $this->validator->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array($violation)));

        $this->violationMapper->expects($this->once())
            ->method('mapViolation')
            ->with($violation, $form, false);

        $this->listener->validateForm(new FormEvent($form, null));
    }

    public function testMapViolationAllowsNonSyncIfInvalid()
    {
        $violation = $this->getConstraintViolation(Form::NOT_SYNCHRONIZED_ERROR);
        $form = $this->getForm('street');

        $this->validator->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(array($violation)));

        $this->violationMapper->expects($this->once())
            ->method('mapViolation')
            // pass true now
            ->with($violation, $form, true);

        $this->listener->validateForm(new FormEvent($form, null));
    }

    public function testValidateIgnoresNonRoot()
    {
        $form = $this->getMockForm();
        $form->expects($this->once())
            ->method('isRoot')
            ->will($this->returnValue(false));

        $this->validator->expects($this->never())
            ->method('validate');

        $this->violationMapper->expects($this->never())
            ->method('mapViolation');

        $this->listener->validateForm(new FormEvent($form, null));
    }

    public function testValidateWithEmptyViolationList()
    {
        $form = $this->getMockForm();
        $form->expects($this->once())
            ->method('isRoot')
            ->will($this->returnValue(true));

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(new ConstraintViolationList()));

        $this->violationMapper
            ->expects($this->never())
            ->method('mapViolation');

        $this->listener->validateForm(new FormEvent($form, null));
    }

    public function testValidatorInterface()
    {
        $validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');

        $listener = new ValidationListener($validator, $this->violationMapper);
        $this->assertAttributeSame($validator, 'validator', $listener);
    }
}
