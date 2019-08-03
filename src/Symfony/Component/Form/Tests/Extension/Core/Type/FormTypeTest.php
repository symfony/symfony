<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Validator\Validation;

class FormTest_AuthorWithoutRefSetter
{
    protected $reference;

    protected $referenceCopy;

    public function __construct($reference)
    {
        $this->reference = $reference;
        $this->referenceCopy = $reference;
    }

    // The returned object should be modified by reference without having
    // to provide a setReference() method
    public function getReference()
    {
        return $this->reference;
    }

    // The returned object is a copy, so setReferenceCopy() must be used
    // to update it
    public function getReferenceCopy()
    {
        return \is_object($this->referenceCopy) ? clone $this->referenceCopy : $this->referenceCopy;
    }

    public function setReferenceCopy($reference)
    {
        $this->referenceCopy = $reference;
    }
}

class FormTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\FormType';

    public function testCreateFormInstances()
    {
        $this->assertInstanceOf('Symfony\Component\Form\Form', $this->factory->create(static::TESTED_TYPE));
    }

    public function testPassRequiredAsOption()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['required' => false]);

        $this->assertFalse($form->isRequired());

        $form = $this->factory->create(static::TESTED_TYPE, null, ['required' => true]);

        $this->assertTrue($form->isRequired());
    }

    public function testSubmittedDataIsTrimmedBeforeTransforming()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'reverse[a]' => 'a',
            ]))
            ->setCompound(false)
            ->getForm();

        $form->submit(' a ');

        $this->assertEquals('a', $form->getViewData());
        $this->assertEquals('reverse[a]', $form->getData());
    }

    public function testSubmittedDataIsNotTrimmedBeforeTransformingIfNoTrimming()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, ['trim' => false])
            ->addViewTransformer(new FixedDataTransformer([
                '' => '',
                'reverse[ a ]' => ' a ',
            ]))
            ->setCompound(false)
            ->getForm();

        $form->submit(' a ');

        $this->assertEquals(' a ', $form->getViewData());
        $this->assertEquals('reverse[ a ]', $form->getData());
    }

    public function testNonReadOnlyFormWithReadOnlyParentIsReadOnly()
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE, null, ['attr' => ['readonly' => true]])
            ->add('child', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertTrue($view['child']->vars['attr']['readonly']);
    }

    public function testReadOnlyFormWithNonReadOnlyParentIsReadOnly()
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE)
            ->add('child', static::TESTED_TYPE, ['attr' => ['readonly' => true]])
            ->getForm()
            ->createView();

        $this->assertTrue($view['child']->vars['attr']['readonly']);
    }

    public function testNonReadOnlyFormWithNonReadOnlyParentIsNotReadOnly()
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE)
            ->add('child', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertArrayNotHasKey('readonly', $view['child']->vars['attr']);
    }

    public function testPassMaxLengthToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, ['attr' => ['maxlength' => 10]])
            ->createView();

        $this->assertSame(10, $view->vars['attr']['maxlength']);
    }

    public function testDataClassMayBeNull()
    {
        $this->assertInstanceOf('Symfony\Component\Form\FormBuilderInterface', $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => null,
        ]));
    }

    public function testDataClassMayBeAbstractClass()
    {
        $this->assertInstanceOf('Symfony\Component\Form\FormBuilderInterface', $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\AbstractAuthor',
        ]));
    }

    public function testDataClassMayBeInterface()
    {
        $this->assertInstanceOf('Symfony\Component\Form\FormBuilderInterface', $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\AuthorInterface',
        ]));
    }

    public function testDataClassMustBeValidClassOrInterface()
    {
        $this->expectException('Symfony\Component\Form\Exception\InvalidArgumentException');
        $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => 'foobar',
        ]);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = [], $expectedData = [])
    {
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }

    public function testSubmitWithEmptyDataCreatesObjectIfClassAvailable()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => false,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        // partially empty, still an object is created
        $form->submit(['firstName' => 'Bernhard', 'lastName' => '']);

        $author = new Author();
        $author->firstName = 'Bernhard';
        $author->setLastName('');

        $this->assertEquals($author, $form->getData());
    }

    public function testSubmitWithDefaultDataDontCreateObject()
    {
        $defaultAuthor = new Author();
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            // data class is inferred from the passed object
            'data' => $defaultAuthor,
            'required' => false,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        // partially empty
        $form->submit(['firstName' => 'Bernhard', 'lastName' => '']);

        $author = new Author();
        $author->firstName = 'Bernhard';
        $author->setLastName('');

        $this->assertEquals($author, $form->getData());
        $this->assertSame($defaultAuthor, $form->getData());
    }

    public function testSubmitWithEmptyDataCreatesArrayIfDataClassIsNull()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => null,
            'required' => false,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(['firstName' => 'Bernhard']);

        $this->assertSame(['firstName' => 'Bernhard'], $form->getData());
    }

    public function testSubmitEmptyWithEmptyDataDontCreateObjectIfNotRequired()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => false,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(['firstName' => '', 'lastName' => '']);

        $this->assertNull($form->getData());
    }

    public function testSubmitEmptyWithEmptyDataCreatesObjectIfRequired()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => true,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(['firstName' => '', 'lastName' => '']);

        $this->assertEquals(new Author(), $form->getData());
    }

    /*
     * We need something to write the field values into
     */
    public function testSubmitWithEmptyDataStoresArrayIfNoClassAvailable()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(['firstName' => 'Bernhard']);

        $this->assertSame(['firstName' => 'Bernhard'], $form->getData());
    }

    public function testSubmitWithEmptyDataPassesEmptyStringToTransformerIfNotCompound()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addViewTransformer(new FixedDataTransformer([
                // required for the initial, internal setData(null)
                '' => 'null',
                // required to test that submit(null) is converted to ''
                'empty' => '',
            ]))
            ->setCompound(false)
            ->getForm();

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('null', $form->getViewData());

        $form->submit(null);

        $this->assertSame('empty', $form->getData());
        $this->assertSame('empty', $form->getNormData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitWithEmptyDataUsesEmptyDataOption()
    {
        $author = new Author();

        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'empty_data' => $author,
        ])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());
        $this->assertNull($form->getViewData());

        $form->submit(['firstName' => 'Bernhard']);

        $this->assertSame($author, $form->getData());
        $this->assertEquals('Bernhard', $author->firstName);
    }

    public function testAttributesException()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException');
        $this->factory->create(static::TESTED_TYPE, null, ['attr' => '']);
    }

    public function testNameCanBeEmptyString()
    {
        $form = $this->factory->createNamed('', static::TESTED_TYPE);

        $this->assertEquals('', $form->getName());
    }

    public function testSubformDoesntCallSettersForReferences()
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder(static::TESTED_TYPE, $author);
        $builder->add('reference', static::TESTED_TYPE, [
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
        ]);
        $builder->get('reference')->add('firstName', TextTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $form->submit([
            // reference has a getter, but no setter
            'reference' => [
                'firstName' => 'Foo',
            ],
        ]);

        $this->assertEquals('Foo', $author->getReference()->firstName);
    }

    public function testSubformCallsSettersIfTheObjectChanged()
    {
        // no reference
        $author = new FormTest_AuthorWithoutRefSetter(null);
        $newReference = new Author();

        $builder = $this->factory->createBuilder(static::TESTED_TYPE, $author);
        $builder->add('referenceCopy', static::TESTED_TYPE, [
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
        ]);
        $builder->get('referenceCopy')->add('firstName', TextTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $form['referenceCopy']->setData($newReference); // new author object

        $form->submit([
            // referenceCopy has a getter that returns a copy
            'referenceCopy' => [
                'firstName' => 'Foo',
        ],
        ]);

        $this->assertEquals('Foo', $author->getReferenceCopy()->firstName);
    }

    public function testSubformCallsSettersIfByReferenceIsFalse()
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder(static::TESTED_TYPE, $author);
        $builder->add('referenceCopy', static::TESTED_TYPE, [
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'by_reference' => false,
        ]);
        $builder->get('referenceCopy')->add('firstName', TextTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $form->submit([
            // referenceCopy has a getter that returns a copy
            'referenceCopy' => [
                'firstName' => 'Foo',
            ],
        ]);

        // firstName can only be updated if setReferenceCopy() was called
        $this->assertEquals('Foo', $author->getReferenceCopy()->firstName);
    }

    public function testSubformCallsSettersIfReferenceIsScalar()
    {
        $author = new FormTest_AuthorWithoutRefSetter('scalar');

        $builder = $this->factory->createBuilder(static::TESTED_TYPE, $author);
        $builder->add('referenceCopy', static::TESTED_TYPE);
        $builder->get('referenceCopy')->addViewTransformer(new CallbackTransformer(
            function () {},
            function ($value) { // reverseTransform
                return 'foobar';
            }
        ));
        $form = $builder->getForm();

        $form->submit([
            'referenceCopy' => [], // doesn't matter actually
        ]);

        // firstName can only be updated if setReferenceCopy() was called
        $this->assertEquals('foobar', $author->getReferenceCopy());
    }

    public function testSubformAlwaysInsertsIntoArrays()
    {
        $ref1 = new Author();
        $ref2 = new Author();
        $author = ['referenceCopy' => $ref1];

        $builder = $this->factory->createBuilder(static::TESTED_TYPE);
        $builder->setData($author);
        $builder->add('referenceCopy', static::TESTED_TYPE);
        $builder->get('referenceCopy')->addViewTransformer(new CallbackTransformer(
            function () {},
            function ($value) use ($ref2) { // reverseTransform
                return $ref2;
            }
        ));
        $form = $builder->getForm();

        $form->submit([
            'referenceCopy' => [], // doesn't matter actually
        ]);

        // the new reference was inserted into the array
        $author = $form->getData();
        $this->assertSame($ref2, $author['referenceCopy']);
    }

    public function testPassMultipartTrueIfAnyChildIsMultipartToView()
    {
        $view = $this->factory->createBuilder(static::TESTED_TYPE)
            ->add('foo', TextTypeTest::TESTED_TYPE)
            ->add('bar', FileTypeTest::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertTrue($view->vars['multipart']);
    }

    public function testViewIsNotRenderedByDefault()
    {
        $view = $this->factory->createBuilder(static::TESTED_TYPE)
            ->add('foo', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertFalse($view->isRendered());
    }

    public function testErrorBubblingIfCompound()
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        $this->assertTrue($form->getConfig()->getErrorBubbling());
    }

    public function testNoErrorBubblingIfNotCompound()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'compound' => false,
        ]);

        $this->assertFalse($form->getConfig()->getErrorBubbling());
    }

    public function testOverrideErrorBubbling()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'compound' => false,
            'error_bubbling' => true,
        ]);

        $this->assertTrue($form->getConfig()->getErrorBubbling());
    }

    public function testPropertyPath()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'property_path' => 'foo',
        ]);

        $this->assertEquals(new PropertyPath('foo'), $form->getPropertyPath());
        $this->assertTrue($form->getConfig()->getMapped());
    }

    public function testPropertyPathNullImpliesDefault()
    {
        $form = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'property_path' => null,
        ]);

        $this->assertEquals(new PropertyPath('name'), $form->getPropertyPath());
        $this->assertTrue($form->getConfig()->getMapped());
    }

    public function testNotMapped()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'property_path' => 'foo',
            'mapped' => false,
        ]);

        $this->assertEquals(new PropertyPath('foo'), $form->getPropertyPath());
        $this->assertFalse($form->getConfig()->getMapped());
    }

    public function testViewValidNotSubmitted()
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->createView();

        $this->assertTrue($view->vars['valid']);
    }

    public function testViewNotValidSubmitted()
    {
        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit([]);
        $form->addError(new FormError('An error'));

        $this->assertFalse($form->createView()->vars['valid']);
    }

    public function testViewSubmittedNotSubmitted()
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->createView();

        $this->assertFalse($view->vars['submitted']);
    }

    public function testViewSubmittedSubmitted()
    {
        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit([]);

        $this->assertTrue($form->createView()->vars['submitted']);
    }

    public function testDataOptionSupersedesSetDataCalls()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'data' => 'default',
            'compound' => false,
        ]);

        $form->setData('foobar');

        $this->assertSame('default', $form->getData());
    }

    public function testPassedDataSupersedesSetDataCalls()
    {
        $form = $this->factory->create(static::TESTED_TYPE, 'default', [
            'compound' => false,
        ]);

        $form->setData('foobar');

        $this->assertSame('default', $form->getData());
    }

    public function testDataOptionSupersedesSetDataCallsIfNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'data' => null,
            'compound' => false,
        ]);

        $form->setData('foobar');

        $this->assertNull($form->getData());
    }

    public function testNormDataIsPassedToView()
    {
        $view = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addModelTransformer(new FixedDataTransformer([
                'foo' => 'bar',
            ]))
            ->addViewTransformer(new FixedDataTransformer([
                'bar' => 'baz',
            ]))
            ->setData('foo')
            ->getForm()
            ->createView();

        $this->assertSame('bar', $view->vars['data']);
        $this->assertSame('baz', $view->vars['value']);
    }

    public function testDataMapperTransformationFailedExceptionInvalidMessageIsUsed()
    {
        $money = new Money(20.5, 'EUR');
        $factory = Forms::createFormFactoryBuilder()
            ->addExtensions([new ValidatorExtension(Validation::createValidator())])
            ->getFormFactory()
        ;

        $builder = $factory
            ->createBuilder(FormType::class, $money, ['invalid_message' => 'not the one to display'])
            ->add('amount', TextType::class)
            ->add('currency', CurrencyType::class)
        ;
        $builder->setDataMapper(new MoneyDataMapper());
        $form = $builder->getForm();

        $form->submit(['amount' => 'invalid_amount', 'currency' => 'USD']);

        $this->assertFalse($form->isValid());
        $this->assertNull($form->getData());
        $this->assertCount(1, $form->getErrors());
        $this->assertSame('Expected numeric value', $form->getTransformationFailure()->getMessage());
        $error = $form->getErrors()[0];
        $this->assertSame('Money amount should be numeric. "invalid_amount" is invalid.', $error->getMessage());
    }

    // https://github.com/symfony/symfony/issues/6862
    public function testPassZeroLabelToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
                'label' => '0',
            ])
            ->createView();

        $this->assertSame('0', $view->vars['label']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull([], [], []);
    }

    public function testPassBlockPrefixToViewWithParent()
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE)
            ->add('child', $this->getTestedType(), [
                'block_prefix' => 'child',
            ])
            ->getForm()
            ->createView();

        $this->assertSame(['form', 'child', '_parent_child'], $view['child']->vars['block_prefixes']);
    }

    public function testDefaultHelpTranslationParameters()
    {
        $view = $this->factory->createNamedBuilder('parent', self::TESTED_TYPE)
            ->add('child', $this->getTestedType())
            ->getForm()
            ->createView();

        $this->assertEquals([], $view['child']->vars['help_translation_parameters']);
    }

    public function testPassHelpTranslationParametersToView()
    {
        $view = $this->factory->create($this->getTestedType(), null, [
            'help_translation_parameters' => ['%param%' => 'value'],
        ])
            ->createView();

        $this->assertSame(['%param%' => 'value'], $view->vars['help_translation_parameters']);
    }

    public function testInheritHelpTranslationParametersFromParent()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', self::TESTED_TYPE, null, [
                'help_translation_parameters' => ['%param%' => 'value'],
            ])
            ->add('child', $this->getTestedType())
            ->getForm()
            ->createView();

        $this->assertEquals(['%param%' => 'value'], $view['child']->vars['help_translation_parameters']);
    }

    public function testPreferOwnHelpTranslationParameters()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', self::TESTED_TYPE, null, [
                'help_translation_parameters' => ['%parent_param%' => 'parent_value', '%override_param%' => 'parent_override_value'],
            ])
            ->add('child', $this->getTestedType(), [
                'help_translation_parameters' => ['%override_param%' => 'child_value'],
            ])
            ->getForm()
            ->createView();

        $this->assertEquals(['%parent_param%' => 'parent_value', '%override_param%' => 'child_value'], $view['child']->vars['help_translation_parameters']);
    }
}

class Money
{
    private $amount;
    private $currency;

    public function __construct($amount, $currency)
    {
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getCurrency()
    {
        return $this->currency;
    }
}

class MoneyDataMapper implements DataMapperInterface
{
    public function mapDataToForms($data, $forms)
    {
        $forms = iterator_to_array($forms);
        $forms['amount']->setData($data ? $data->getAmount() : 0);
        $forms['currency']->setData($data ? $data->getCurrency() : 'EUR');
    }

    public function mapFormsToData($forms, &$data)
    {
        $forms = iterator_to_array($forms);

        $amount = $forms['amount']->getData();
        if (!is_numeric($amount)) {
            $failure = new TransformationFailedException('Expected numeric value');
            $failure->setInvalidMessage('Money amount should be numeric. {{ amount }} is invalid.', ['{{ amount }}' => json_encode($amount)]);

            throw $failure;
        }

        $data = new Money(
            $forms['amount']->getData(),
            $forms['currency']->getData()
        );
    }
}
