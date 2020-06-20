<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Constraints;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Form\Extension\Validator\Constraints\FormValidator;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\SubmitButtonBuilder;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var FormFactoryInterface
     */
    private $factory;

    protected function setUp()
    {
        $this->dispatcher = new EventDispatcher();
        $this->factory = (new FormFactoryBuilder())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        parent::setUp();

        $this->constraint = new Form();
    }

    public function testValidate()
    {
        $object = new \stdClass();
        $options = ['validation_groups' => ['group1', 'group2']];
        $form = $this->getCompoundForm($object, $options);
        $form->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['group1', 'group2']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testValidateConstraints()
    {
        $object = new \stdClass();
        $constraint1 = new NotNull(['groups' => ['group1', 'group2']]);
        $constraint2 = new NotBlank(['groups' => 'group2']);

        $options = [
            'validation_groups' => ['group1', 'group2'],
            'constraints' => [$constraint1, $constraint2],
        ];
        $form = $this->getCompoundForm($object, $options);
        $form->submit([]);

        // First default constraints
        $this->expectValidateAt(0, 'data', $object, ['group1', 'group2']);

        // Then custom constraints
        $this->expectValidateValueAt(1, 'data', $object, $constraint1, 'group1');
        $this->expectValidateValueAt(2, 'data', $object, $constraint2, 'group2');

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testValidateChildIfValidConstraint()
    {
        $object = new \stdClass();

        $parent = $this->getBuilder('parent')
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $options = [
            'validation_groups' => ['group1', 'group2'],
            'constraints' => [new Valid()],
        ];
        $form = $this->getCompoundForm($object, $options);
        $parent->add($form);
        $parent->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['group1', 'group2']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testDontValidateIfParentWithoutValidConstraint()
    {
        $object = new \stdClass();

        $parent = $this->getBuilder('parent', null)
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $options = ['validation_groups' => ['group1', 'group2']];
        $form = $this->getBuilder('name', '\stdClass', $options)->getForm();
        $parent->add($form);

        $form->setData($object);
        $parent->submit([]);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isSynchronized());
        $this->expectNoValidate();

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testMissingConstraintIndex()
    {
        $object = new \stdClass();
        $form = $this->getCompoundForm($object);
        $form->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['Default']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testValidateConstraintsOptionEvenIfNoValidConstraint()
    {
        $object = new \stdClass();
        $constraint1 = new NotNull(['groups' => ['group1', 'group2']]);
        $constraint2 = new NotBlank(['groups' => 'group2']);

        $parent = $this->getBuilder('parent', null)
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $options = [
            'validation_groups' => ['group1', 'group2'],
            'constraints' => [$constraint1, $constraint2],
        ];
        $form = $this->getCompoundForm($object, $options);
        $parent->add($form);
        $parent->submit([]);

        $this->expectValidateValueAt(0, 'data', $object, $constraint1, 'group1');
        $this->expectValidateValueAt(1, 'data', $object, $constraint2, 'group2');

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testDontValidateIfNoValidationGroups()
    {
        $object = new \stdClass();

        $form = $this->getBuilder('name', '\stdClass', [
                'validation_groups' => [],
            ])
            ->setData($object)
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();

        $form->setData($object);
        $form->submit([]);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isSynchronized());
        $this->expectNoValidate();

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testDontValidateConstraintsIfNoValidationGroups()
    {
        $object = new \stdClass();

        $options = [
            'validation_groups' => [],
            'constraints' => [new NotBlank(), new NotNull()],
        ];
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->getForm();

        // Launch transformer
        $form->submit('foo');

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isSynchronized());
        $this->expectNoValidate();

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testDontValidateChildConstraintsIfCallableNoValidationGroups()
    {
        $formOptions = [
            'constraints' => [new Valid()],
            'validation_groups' => [],
        ];
        $form = $this->getBuilder('name', null, $formOptions)
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $childOptions = ['constraints' => [new NotBlank()]];
        $child = $this->getCompoundForm(new \stdClass(), $childOptions);
        $form->add($child);
        $form->submit([]);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isSynchronized());
        $this->expectNoValidate();

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testDontValidateIfNotSynchronized()
    {
        $object = new \stdClass();

        $form = $this->getBuilder('name', '\stdClass', [
                'invalid_message' => 'invalid_message_key',
                // Invalid message parameters must be supported, because the
                // invalid message can be a translation key
                // see https://github.com/symfony/symfony/issues/5144
                'invalid_message_parameters' => ['{{ foo }}' => 'bar'],
            ])
            ->setData($object)
            ->addViewTransformer(new CallbackTransformer(
                function ($data) { return $data; },
                function () { throw new TransformationFailedException(); }
            ))
            ->getForm();

        // Launch transformer
        $form->submit('foo');

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isSynchronized());
        $this->expectNoValidate();

        $this->validator->validate($form, new Form());

        $this->buildViolation('invalid_message_key')
            ->setParameter('{{ value }}', 'foo')
            ->setParameter('{{ foo }}', 'bar')
            ->setInvalidValue('foo')
            ->setCode(Form::NOT_SYNCHRONIZED_ERROR)
            ->setCause($form->getTransformationFailure())
            ->assertRaised();
    }

    public function testAddInvalidErrorEvenIfNoValidationGroups()
    {
        $object = new \stdClass();

        $form = $this->getBuilder('name', '\stdClass', [
                'invalid_message' => 'invalid_message_key',
                // Invalid message parameters must be supported, because the
                // invalid message can be a translation key
                // see https://github.com/symfony/symfony/issues/5144
                'invalid_message_parameters' => ['{{ foo }}' => 'bar'],
                'validation_groups' => [],
            ])
            ->setData($object)
            ->addViewTransformer(new CallbackTransformer(
                    function ($data) { return $data; },
                    function () { throw new TransformationFailedException(); }
                ))
            ->getForm();

        // Launch transformer
        $form->submit('foo');

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isSynchronized());
        $this->expectNoValidate();

        $this->validator->validate($form, new Form());

        $this->buildViolation('invalid_message_key')
            ->setParameter('{{ value }}', 'foo')
            ->setParameter('{{ foo }}', 'bar')
            ->setInvalidValue('foo')
            ->setCode(Form::NOT_SYNCHRONIZED_ERROR)
            ->setCause($form->getTransformationFailure())
            ->assertRaised();
    }

    public function testDontValidateConstraintsIfNotSynchronized()
    {
        $object = new \stdClass();

        $options = [
            'invalid_message' => 'invalid_message_key',
            'validation_groups' => ['group1', 'group2'],
            'constraints' => [new NotBlank(), new NotBlank()],
        ];
        $form = $this->getBuilder('name', '\stdClass', $options)
            ->setData($object)
            ->addViewTransformer(new CallbackTransformer(
                function ($data) { return $data; },
                function () { throw new TransformationFailedException(); }
            ))
            ->getForm();

        // Launch transformer
        $form->submit('foo');

        $this->expectNoValidate();

        $this->validator->validate($form, new Form());

        $this->buildViolation('invalid_message_key')
            ->setParameter('{{ value }}', 'foo')
            ->setInvalidValue('foo')
            ->setCode(Form::NOT_SYNCHRONIZED_ERROR)
            ->setCause($form->getTransformationFailure())
            ->assertRaised();
    }

    public function testHandleGroupSequenceValidationGroups()
    {
        $object = new \stdClass();
        $options = ['validation_groups' => new GroupSequence(['group1', 'group2'])];
        $form = $this->getCompoundForm($object, $options);
        $form->submit([]);

        $this->expectValidateAt(0, 'data', $object, 'group1');
        $this->expectValidateAt(1, 'data', $object, 'group2');

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testHandleCallbackValidationGroups()
    {
        $object = new \stdClass();
        $options = ['validation_groups' => [$this, 'getValidationGroups']];
        $form = $this->getCompoundForm($object, $options);
        $form->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['group1', 'group2']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testDontExecuteFunctionNames()
    {
        $object = new \stdClass();
        $options = ['validation_groups' => 'header'];
        $form = $this->getCompoundForm($object, $options);
        $form->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['header']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testHandleClosureValidationGroups()
    {
        $object = new \stdClass();
        $options = ['validation_groups' => function (FormInterface $form) {
            return ['group1', 'group2'];
        }];
        $form = $this->getCompoundForm($object, $options);
        $form->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['group1', 'group2']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testUseValidationGroupOfClickedButton()
    {
        $object = new \stdClass();

        $parent = $this->getBuilder('parent')
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $form = $this->getForm('name', '\stdClass', [
            'validation_groups' => 'form_group',
            'constraints' => [new Valid()],
        ]);

        $parent->add($form);
        $parent->add($this->getSubmitButton('submit', [
            'validation_groups' => 'button_group',
        ]));

        $parent->submit(['name' => $object, 'submit' => '']);

        $this->expectValidateAt(0, 'data', $object, ['button_group']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testDontUseValidationGroupOfUnclickedButton()
    {
        $object = new \stdClass();

        $parent = $this->getBuilder('parent')
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $form = $this->getCompoundForm($object, [
            'validation_groups' => 'form_group',
            'constraints' => [new Valid()],
        ]);

        $parent->add($form);
        $parent->add($this->getSubmitButton('submit', [
            'validation_groups' => 'button_group',
        ]));

        $parent->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['form_group']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testUseInheritedValidationGroup()
    {
        $object = new \stdClass();

        $parentOptions = ['validation_groups' => 'group'];
        $parent = $this->getBuilder('parent', null, $parentOptions)
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $formOptions = ['constraints' => [new Valid()]];
        $form = $this->getCompoundForm($object, $formOptions);
        $parent->add($form);
        $parent->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['group']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testUseInheritedCallbackValidationGroup()
    {
        $object = new \stdClass();

        $parentOptions = ['validation_groups' => [$this, 'getValidationGroups']];
        $parent = $this->getBuilder('parent', null, $parentOptions)
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $formOptions = ['constraints' => [new Valid()]];
        $form = $this->getCompoundForm($object, $formOptions);
        $parent->add($form);
        $parent->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['group1', 'group2']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testUseInheritedClosureValidationGroup()
    {
        $object = new \stdClass();

        $parentOptions = [
            'validation_groups' => function () {
                return ['group1', 'group2'];
            },
        ];
        $parent = $this->getBuilder('parent', null, $parentOptions)
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $formOptions = ['constraints' => [new Valid()]];
        $form = $this->getCompoundForm($object, $formOptions);
        $parent->add($form);
        $parent->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['group1', 'group2']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testAppendPropertyPath()
    {
        $object = new \stdClass();
        $form = $this->getCompoundForm($object);
        $form->submit([]);

        $this->expectValidateAt(0, 'data', $object, ['Default']);

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testDontWalkScalars()
    {
        $form = $this->getBuilder()
            ->setData('scalar')
            ->getForm();
        $form->submit('foo');

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isSynchronized());
        $this->expectNoValidate();

        $this->validator->validate($form, new Form());

        $this->assertNoViolation();
    }

    public function testViolationIfExtraData()
    {
        $form = $this->getBuilder('parent', null, ['extra_fields_message' => 'Extra!'])
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->add($this->getBuilder('child'))
            ->getForm();

        $form->submit(['foo' => 'bar']);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isSynchronized());

        $this->expectValidateValueAt(0, 'children[child]', $form->get('child'), new Form());

        $this->validator->validate($form, new Form());

        $this->buildViolation('Extra!')
            ->setParameter('{{ extra_fields }}', '"foo"')
            ->setInvalidValue(['foo' => 'bar'])
            ->setCode(Form::NO_SUCH_FIELD_ERROR)
            ->assertRaised();
    }

    public function testViolationFormatIfMultipleExtraFields()
    {
        $form = $this->getBuilder('parent', null, ['extra_fields_message' => 'Extra!'])
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->add($this->getBuilder('child'))
            ->getForm();

        $form->submit(['foo' => 'bar', 'baz' => 'qux', 'quux' => 'quuz']);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isSynchronized());

        $this->expectValidateValueAt(0, 'children[child]', $form->get('child'), new Form());

        $this->validator->validate($form, new Form());

        $this->buildViolation('Extra!')
            ->setParameter('{{ extra_fields }}', '"foo", "baz", "quux"')
            ->setInvalidValue(['foo' => 'bar', 'baz' => 'qux', 'quux' => 'quuz'])
            ->setCode(Form::NO_SUCH_FIELD_ERROR)
            ->assertRaised();
    }

    public function testNoViolationIfAllowExtraData()
    {
        $form = $this
            ->getBuilder('parent', null, ['allow_extra_fields' => true])
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->add($this->getBuilder('child'))
            ->getForm();

        $context = new ExecutionContext(Validation::createValidator(), $form, new IdentityTranslator());

        $form->submit(['foo' => 'bar']);

        $this->validator->initialize($context);
        $this->validator->validate($form, new Form());

        $this->assertCount(0, $context->getViolations());
    }

    /**
     * Access has to be public, as this method is called via callback array
     * in {@link testValidateFormDataCanHandleCallbackValidationGroups()}
     * and {@link testValidateFormDataUsesInheritedCallbackValidationGroup()}.
     */
    public function getValidationGroups(FormInterface $form)
    {
        return ['group1', 'group2'];
    }

    public function testCauseForNotAllowedExtraFieldsIsTheFormConstraint()
    {
        $form = $this
            ->getBuilder('form', null, ['constraints' => [new NotBlank(['groups' => ['foo']])]])
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $form->submit([
            'extra_data' => 'foo',
        ]);

        $context = new ExecutionContext(Validation::createValidator(), $form, new IdentityTranslator());
        $constraint = new Form();

        $this->validator->initialize($context);
        $this->validator->validate($form, $constraint);

        $this->assertCount(1, $context->getViolations());
        $this->assertSame($constraint, $context->getViolations()->get(0)->getConstraint());
    }

    protected function createValidator()
    {
        return new FormValidator();
    }

    /**
     * @param string $name
     * @param string $dataClass
     *
     * @return FormBuilder
     */
    private function getBuilder($name = 'name', $dataClass = null, array $options = [])
    {
        $options = array_replace([
            'constraints' => [],
            'invalid_message_parameters' => [],
        ], $options);

        return new FormBuilder($name, $dataClass, $this->dispatcher, $this->factory, $options);
    }

    private function getForm($name = 'name', $dataClass = null, array $options = [])
    {
        return $this->getBuilder($name, $dataClass, $options)->getForm();
    }

    private function getCompoundForm($data, array $options = [])
    {
        return $this->getBuilder('name', \is_object($data) ? \get_class($data) : null, $options)
            ->setData($data)
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
    }

    private function getSubmitButton($name = 'name', array $options = [])
    {
        $builder = new SubmitButtonBuilder($name, $options);

        return $builder->getForm();
    }
}
