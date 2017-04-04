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

use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\FixedDataTransformer;
use Symfony\Component\Form\FormError;

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
        return is_object($this->referenceCopy) ? clone $this->referenceCopy : $this->referenceCopy;
    }

    public function setReferenceCopy($reference)
    {
        $this->referenceCopy = $reference;
    }
}

class FormTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\FormType';

    /**
     * @group legacy
     */
    public function testLegacyName()
    {
        $form = $this->factory->create('form');

        $this->assertSame('form', $form->getConfig()->getType()->getName());
    }

    public function testCreateFormInstances()
    {
        $this->assertInstanceOf('Symfony\Component\Form\Form', $this->factory->create(static::TESTED_TYPE));
    }

    public function testPassRequiredAsOption()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array('required' => false));

        $this->assertFalse($form->isRequired());

        $form = $this->factory->create(static::TESTED_TYPE, null, array('required' => true));

        $this->assertTrue($form->isRequired());
    }

    public function testSubmittedDataIsTrimmedBeforeTransforming()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'reverse[a]' => 'a',
            )))
            ->setCompound(false)
            ->getForm();

        $form->submit(' a ');

        $this->assertEquals('a', $form->getViewData());
        $this->assertEquals('reverse[a]', $form->getData());
    }

    public function testSubmittedDataIsNotTrimmedBeforeTransformingIfNoTrimming()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, array('trim' => false))
            ->addViewTransformer(new FixedDataTransformer(array(
                '' => '',
                'reverse[ a ]' => ' a ',
            )))
            ->setCompound(false)
            ->getForm();

        $form->submit(' a ');

        $this->assertEquals(' a ', $form->getViewData());
        $this->assertEquals('reverse[ a ]', $form->getData());
    }

    /**
     * @group legacy
     */
    public function testLegacyNonReadOnlyFormWithReadOnlyParentIsReadOnly()
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE, null, array('read_only' => true))
            ->add('child', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertTrue($view['child']->vars['read_only']);
    }

    public function testNonReadOnlyFormWithReadOnlyParentIsReadOnly()
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE, null, array('attr' => array('readonly' => true)))
            ->add('child', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertTrue($view['child']->vars['attr']['readonly']);
    }

    /**
     * @group legacy
     */
    public function testLegacyReadOnlyFormWithNonReadOnlyParentIsReadOnly()
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE)
            ->add('child', static::TESTED_TYPE, array('read_only' => true))
            ->getForm()
            ->createView();

        $this->assertTrue($view['child']->vars['read_only']);
    }

    public function testReadOnlyFormWithNonReadOnlyParentIsReadOnly()
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE)
            ->add('child', static::TESTED_TYPE, array('attr' => array('readonly' => true)))
            ->getForm()
            ->createView();

        $this->assertTrue($view['child']->vars['attr']['readonly']);
    }

    /**
     * @group legacy
     */
    public function testLegacyNonReadOnlyFormWithNonReadOnlyParentIsNotReadOnly()
    {
        $view = $this->factory->createNamedBuilder('parent', static::TESTED_TYPE)
                ->add('child', static::TESTED_TYPE)
                ->getForm()
                ->createView();

        $this->assertFalse($view['child']->vars['read_only']);
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
        $view = $this->factory->create(static::TESTED_TYPE, null, array('attr' => array('maxlength' => 10)))
            ->createView();

        $this->assertSame(10, $view->vars['attr']['maxlength']);
    }

    public function testPassMaxLengthBCToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array('max_length' => 10))
            ->createView();

        $this->assertSame(10, $view->vars['attr']['maxlength']);
    }

    public function testDataClassMayBeNull()
    {
        $this->assertInstanceOf('Symfony\Component\Form\FormBuilderInterface', $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'data_class' => null,
        )));
    }

    public function testDataClassMayBeAbstractClass()
    {
        $this->assertInstanceOf('Symfony\Component\Form\FormBuilderInterface', $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\AbstractAuthor',
        )));
    }

    public function testDataClassMayBeInterface()
    {
        $this->assertInstanceOf('Symfony\Component\Form\FormBuilderInterface', $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\AuthorInterface',
        )));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testDataClassMustBeValidClassOrInterface()
    {
        $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'data_class' => 'foobar',
        ));
    }

    public function testSubmitWithEmptyDataCreatesObjectIfClassAvailable()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => false,
        ))
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        // partially empty, still an object is created
        $form->submit(array('firstName' => 'Bernhard', 'lastName' => ''));

        $author = new Author();
        $author->firstName = 'Bernhard';
        $author->setLastName('');

        $this->assertEquals($author, $form->getData());
    }

    public function testSubmitWithDefaultDataDontCreateObject()
    {
        $defaultAuthor = new Author();
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            // data class is inferred from the passed object
            'data' => $defaultAuthor,
            'required' => false,
        ))
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        // partially empty
        $form->submit(array('firstName' => 'Bernhard', 'lastName' => ''));

        $author = new Author();
        $author->firstName = 'Bernhard';
        $author->setLastName('');

        $this->assertEquals($author, $form->getData());
        $this->assertSame($defaultAuthor, $form->getData());
    }

    public function testSubmitWithEmptyDataCreatesArrayIfDataClassIsNull()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'data_class' => null,
            'required' => false,
        ))
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(array('firstName' => 'Bernhard'));

        $this->assertSame(array('firstName' => 'Bernhard'), $form->getData());
    }

    public function testSubmitEmptyWithEmptyDataDontCreateObjectIfNotRequired()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => false,
        ))
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(array('firstName' => '', 'lastName' => ''));

        $this->assertNull($form->getData());
    }

    public function testSubmitEmptyWithEmptyDataCreatesObjectIfRequired()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => true,
        ))
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());

        $form->submit(array('firstName' => '', 'lastName' => ''));

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

        $form->submit(array('firstName' => 'Bernhard'));

        $this->assertSame(array('firstName' => 'Bernhard'), $form->getData());
    }

    public function testSubmitWithEmptyDataPassesEmptyStringToTransformerIfNotCompound()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addViewTransformer(new FixedDataTransformer(array(
                // required for the initial, internal setData(null)
                '' => 'null',
                // required to test that submit(null) is converted to ''
                'empty' => '',
            )))
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

        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'empty_data' => $author,
        ))
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertNull($form->getData());
        $this->assertNull($form->getViewData());

        $form->submit(array('firstName' => 'Bernhard'));

        $this->assertSame($author, $form->getData());
        $this->assertEquals('Bernhard', $author->firstName);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testAttributesException()
    {
        $this->factory->create(static::TESTED_TYPE, null, array('attr' => ''));
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
        $builder->add('reference', static::TESTED_TYPE, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
        ));
        $builder->get('reference')->add('firstName', TextTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $form->submit(array(
            // reference has a getter, but no setter
            'reference' => array(
                'firstName' => 'Foo',
            ),
        ));

        $this->assertEquals('Foo', $author->getReference()->firstName);
    }

    public function testSubformCallsSettersIfTheObjectChanged()
    {
        // no reference
        $author = new FormTest_AuthorWithoutRefSetter(null);
        $newReference = new Author();

        $builder = $this->factory->createBuilder(static::TESTED_TYPE, $author);
        $builder->add('referenceCopy', static::TESTED_TYPE, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
        ));
        $builder->get('referenceCopy')->add('firstName', TextTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $form['referenceCopy']->setData($newReference); // new author object

        $form->submit(array(
            // referenceCopy has a getter that returns a copy
            'referenceCopy' => array(
                'firstName' => 'Foo',
        ),
        ));

        $this->assertEquals('Foo', $author->getReferenceCopy()->firstName);
    }

    public function testSubformCallsSettersIfByReferenceIsFalse()
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder(static::TESTED_TYPE, $author);
        $builder->add('referenceCopy', static::TESTED_TYPE, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'by_reference' => false,
        ));
        $builder->get('referenceCopy')->add('firstName', TextTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $form->submit(array(
            // referenceCopy has a getter that returns a copy
            'referenceCopy' => array(
                'firstName' => 'Foo',
            ),
        ));

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

        $form->submit(array(
            'referenceCopy' => array(), // doesn't matter actually
        ));

        // firstName can only be updated if setReferenceCopy() was called
        $this->assertEquals('foobar', $author->getReferenceCopy());
    }

    public function testSubformAlwaysInsertsIntoArrays()
    {
        $ref1 = new Author();
        $ref2 = new Author();
        $author = array('referenceCopy' => $ref1);

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

        $form->submit(array(
            'referenceCopy' => array(), // doesn't matter actually
        ));

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
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'compound' => false,
        ));

        $this->assertFalse($form->getConfig()->getErrorBubbling());
    }

    public function testOverrideErrorBubbling()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'compound' => false,
            'error_bubbling' => true,
        ));

        $this->assertTrue($form->getConfig()->getErrorBubbling());
    }

    public function testPropertyPath()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'property_path' => 'foo',
        ));

        $this->assertEquals(new PropertyPath('foo'), $form->getPropertyPath());
        $this->assertTrue($form->getConfig()->getMapped());
    }

    public function testPropertyPathNullImpliesDefault()
    {
        $form = $this->factory->createNamed('name', static::TESTED_TYPE, null, array(
            'property_path' => null,
        ));

        $this->assertEquals(new PropertyPath('name'), $form->getPropertyPath());
        $this->assertTrue($form->getConfig()->getMapped());
    }

    public function testNotMapped()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'property_path' => 'foo',
            'mapped' => false,
        ));

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
        $form->submit(array());
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
        $form->submit(array());

        $this->assertTrue($form->createView()->vars['submitted']);
    }

    public function testDataOptionSupersedesSetDataCalls()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'data' => 'default',
            'compound' => false,
        ));

        $form->setData('foobar');

        $this->assertSame('default', $form->getData());
    }

    public function testPassedDataSupersedesSetDataCalls()
    {
        $form = $this->factory->create(static::TESTED_TYPE, 'default', array(
            'compound' => false,
        ));

        $form->setData('foobar');

        $this->assertSame('default', $form->getData());
    }

    public function testDataOptionSupersedesSetDataCallsIfNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'data' => null,
            'compound' => false,
        ));

        $form->setData('foobar');

        $this->assertNull($form->getData());
    }

    public function testNormDataIsPassedToView()
    {
        $view = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addModelTransformer(new FixedDataTransformer(array(
                'foo' => 'bar',
            )))
            ->addViewTransformer(new FixedDataTransformer(array(
                'bar' => 'baz',
            )))
            ->setData('foo')
            ->getForm()
            ->createView();

        $this->assertSame('bar', $view->vars['data']);
        $this->assertSame('baz', $view->vars['value']);
    }

    // https://github.com/symfony/symfony/issues/6862
    public function testPassZeroLabelToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array(
                'label' => '0',
            ))
            ->createView();

        $this->assertSame('0', $view->vars['label']);
    }

    /**
     * @group legacy
     */
    public function testCanGetErrorsWhenButtonInForm()
    {
        $builder = $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => false,
        ));
        $builder->add('foo', 'text');
        $builder->add('submit', 'submit');
        $form = $builder->getForm();

        //This method should not throw a Fatal Error Exception.
        $this->assertInternalType('string', $form->getErrorsAsString());
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull(array(), array(), array());
    }
}
