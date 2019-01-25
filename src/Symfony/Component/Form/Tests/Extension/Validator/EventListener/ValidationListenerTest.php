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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Extension\Validator\Constraints\Form as FormConstraint;
use Symfony\Component\Form\Extension\Validator\EventListener\ValidationListener;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class ValidationListenerTest extends TestCase
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
        $this->dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->factory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
        $this->validator = $this->getMockBuilder('Symfony\Component\Validator\Validator\ValidatorInterface')->getMock();
        $this->violationMapper = $this->getMockBuilder('Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapperInterface')->getMock();
        $this->listener = new ValidationListener($this->validator, $this->violationMapper);
        $this->message = 'Message';
        $this->messageTemplate = 'Message template';
        $this->params = ['foo' => 'bar'];
    }

    private function getConstraintViolation($code = null)
    {
        return new ConstraintViolation($this->message, $this->messageTemplate, $this->params, null, 'prop.path', null, null, $code, new FormConstraint());
    }

    private function getBuilder($name = 'name', $propertyPath = null, $dataClass = null)
    {
        $builder = new FormBuilder($name, $dataClass, $this->dispatcher, $this->factory);
        $builder->setPropertyPath(new PropertyPath($propertyPath ?: $name));
        $builder->setAttribute('error_mapping', []);
        $builder->setErrorBubbling(false);
        $builder->setMapped(true);

        return $builder;
    }

    private function getForm($name = 'name', $propertyPath = null, $dataClass = null)
    {
        return $this->getBuilder($name, $propertyPath, $dataClass)->getForm();
    }

    private function createForm($name = '', $compound = false)
    {
        $config = new FormBuilder($name, null, $this->getMockBuilder(EventDispatcherInterface::class)->getMock(), $this->getMockBuilder(FormFactoryInterface::class)->getMock());
        $config->setCompound($compound);

        if ($compound) {
            $config->setDataMapper(new PropertyPathMapper());
        }

        return new Form($config);
    }

    // More specific mapping tests can be found in ViolationMapperTest
    public function testMapViolation()
    {
        $violation = $this->getConstraintViolation();
        $form = $this->getForm('street');

        $this->validator->expects($this->once())
            ->method('validate')
            ->will($this->returnValue([$violation]));

        $this->violationMapper->expects($this->once())
            ->method('mapViolation')
            ->with($violation, $form, false);

        $this->listener->validateForm(new FormEvent($form, null));
    }

    public function testMapViolationAllowsNonSyncIfInvalid()
    {
        $violation = $this->getConstraintViolation(FormConstraint::NOT_SYNCHRONIZED_ERROR);
        $form = $this->getForm('street');

        $this->validator->expects($this->once())
            ->method('validate')
            ->will($this->returnValue([$violation]));

        $this->violationMapper->expects($this->once())
            ->method('mapViolation')
            // pass true now
            ->with($violation, $form, true);

        $this->listener->validateForm(new FormEvent($form, null));
    }

    public function testValidateIgnoresNonRoot()
    {
        $childForm = $this->createForm('child');

        $form = $this->createForm('', true);
        $form->add($childForm);

        $this->validator->expects($this->never())
            ->method('validate');

        $this->violationMapper->expects($this->never())
            ->method('mapViolation');

        $this->listener->validateForm(new FormEvent($childForm, null));
    }

    public function testValidateWithEmptyViolationList()
    {
        $form = $this->createForm();

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
        $validator = $this->getMockBuilder('Symfony\Component\Validator\Validator\ValidatorInterface')->getMock();

        $listener = new ValidationListener($validator, $this->violationMapper);
        $this->assertAttributeSame($validator, 'validator', $listener);
    }
}
