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

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Form;
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

class FormTypeTest extends TypeTestCase
{
    public function testPassRequiredAsOption()
    {
        $form = $this->factory->create('form', null, array('required' => false));

        $this->assertFalse($form->isRequired());

        $form = $this->factory->create('form', null, array('required' => true));

        $this->assertTrue($form->isRequired());
    }

    public function testPassDisabledAsOption()
    {
        $form = $this->factory->create('form', null, array('disabled' => true));

        $this->assertTrue($form->isDisabled());
    }

    public function testBoundDataIsTrimmedBeforeTransforming()
    {
        $form = $this->factory->createBuilder('form')
            ->addViewTransformer(new FixedDataTransformer(array(
                null => '',
                'reverse[a]' => 'a',
            )))
            ->setCompound(false)
            ->getForm();

        $form->bind(' a ');

        $this->assertEquals('a', $form->getViewData());
        $this->assertEquals('reverse[a]', $form->getData());
    }

    public function testBoundDataIsNotTrimmedBeforeTransformingIfNoTrimming()
    {
        $form = $this->factory->createBuilder('form', null, array('trim' => false))
            ->addViewTransformer(new FixedDataTransformer(array(
                null => '',
                'reverse[ a ]' => ' a ',
            )))
            ->setCompound(false)
            ->getForm();

        $form->bind(' a ');

        $this->assertEquals(' a ', $form->getViewData());
        $this->assertEquals('reverse[ a ]', $form->getData());
    }

    public function testPassIdAndNameToView()
    {
        $form = $this->factory->createNamed('name', 'form');
        $view = $form->createView();

        $this->assertEquals('name', $view->vars['id']);
        $this->assertEquals('name', $view->vars['name']);
        $this->assertEquals('name', $view->vars['full_name']);
    }

    public function testStripLeadingUnderscoresAndDigitsFromId()
    {
        $form = $this->factory->createNamed('_09name', 'form');
        $view = $form->createView();

        $this->assertEquals('name', $view->vars['id']);
        $this->assertEquals('_09name', $view->vars['name']);
        $this->assertEquals('_09name', $view->vars['full_name']);
    }

    public function testPassIdAndNameToViewWithParent()
    {
        $parent = $this->factory->createNamed('parent', 'form');
        $parent->add($this->factory->createNamed('child', 'form'));
        $view = $parent->createView();

        $this->assertEquals('parent_child', $view['child']->vars['id']);
        $this->assertEquals('child', $view['child']->vars['name']);
        $this->assertEquals('parent[child]', $view['child']->vars['full_name']);
    }

    public function testPassIdAndNameToViewWithGrandParent()
    {
        $parent = $this->factory->createNamed('parent', 'form');
        $parent->add($this->factory->createNamed('child', 'form'));
        $parent['child']->add($this->factory->createNamed('grand_child', 'form'));
        $view = $parent->createView();

        $this->assertEquals('parent_child_grand_child', $view['child']['grand_child']->vars['id']);
        $this->assertEquals('grand_child', $view['child']['grand_child']->vars['name']);
        $this->assertEquals('parent[child][grand_child]', $view['child']['grand_child']->vars['full_name']);
    }

    public function testNonReadOnlyFormWithReadOnlyParentBeingReadOnly()
    {
        $parent = $this->factory->createNamed('parent', 'form', null, array('read_only' => true));
        $child  = $this->factory->createNamed('child', 'form');
        $view   = $parent->add($child)->createView();

        $this->assertTrue($view['child']->vars['read_only']);
    }

    public function testReadOnlyFormWithNonReadOnlyParentBeingReadOnly()
    {
        $parent = $this->factory->createNamed('parent', 'form');
        $child  = $this->factory->createNamed('child', 'form', null, array('read_only' => true));
        $view   = $parent->add($child)->createView();

        $this->assertTrue($view['child']->vars['read_only']);
    }

    public function testNonReadOnlyFormWithNonReadOnlyParentBeingNonReadOnly()
    {
        $parent = $this->factory->createNamed('parent', 'form');
        $child  = $this->factory->createNamed('child', 'form');
        $view   = $parent->add($child)->createView();

        $this->assertFalse($view['child']->vars['read_only']);
    }

    public function testPassMaxLengthToView()
    {
        $form = $this->factory->create('form', null, array('max_length' => 10));
        $view = $form->createView();

        $this->assertSame(10, $view->vars['max_length']);
    }

    public function testPassTranslationDomainToView()
    {
        $form = $this->factory->create('form', null, array('translation_domain' => 'test'));
        $view = $form->createView();

        $this->assertSame('test', $view->vars['translation_domain']);
    }

    public function testNonTranslationDomainFormWithTranslationDomainParentBeingTranslationDomain()
    {
        $parent = $this->factory->createNamed('parent', 'form', null, array('translation_domain' => 'test'));
        $child  = $this->factory->createNamed('child', 'form');
        $view   = $parent->add($child)->createView();

        $this->assertEquals('test', $view['child']->vars['translation_domain']);
    }

    public function testTranslationDomainFormWithNonTranslationDomainParentBeingTranslationDomain()
    {
        $parent = $this->factory->createNamed('parent', 'form');
        $child  = $this->factory->createNamed('child', 'form', null, array('translation_domain' => 'test'));
        $view   = $parent->add($child)->createView();

        $this->assertEquals('test', $view['child']->vars['translation_domain']);
    }

    public function testNonTranslationDomainFormWithNonTranslationDomainParentBeingTranslationDomainDefault()
    {
        $parent = $this->factory->createNamed('parent', 'form');
        $child  = $this->factory->createNamed('child', 'form');
        $view   = $parent->add($child)->createView();

        $this->assertEquals('messages', $view['child']->vars['translation_domain']);
    }

    public function testPassLabelToView()
    {
        $form = $this->factory->createNamed('__test___field', 'form', null, array('label' => 'My label'));
        $view = $form->createView();

        $this->assertSame('My label', $view->vars['label']);
    }

    public function testDefaultTranslationDomain()
    {
        $form = $this->factory->create('form');
        $view = $form->createView();

        $this->assertSame('messages', $view->vars['translation_domain']);
    }

    public function testBindWithEmptyDataCreatesObjectIfClassAvailable()
    {
        $form = $this->factory->create('form', null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => false,
        ));
        $form->add($this->factory->createNamed('firstName', 'text'));
        $form->add($this->factory->createNamed('lastName', 'text'));

        $form->setData(null);
        // partially empty, still an object is created
        $form->bind(array('firstName' => 'Bernhard', 'lastName' => ''));

        $author = new Author();
        $author->firstName = 'Bernhard';
        $author->setLastName('');

        $this->assertEquals($author, $form->getData());
    }

    public function testBindWithEmptyDataCreatesObjectIfInitiallyBoundWithObject()
    {
        $form = $this->factory->create('form', null, array(
            // data class is inferred from the passed object
            'data' => new Author(),
            'required' => false,
        ));
        $form->add($this->factory->createNamed('firstName', 'text'));
        $form->add($this->factory->createNamed('lastName', 'text'));

        $form->setData(null);
        // partially empty, still an object is created
        $form->bind(array('firstName' => 'Bernhard', 'lastName' => ''));

        $author = new Author();
        $author->firstName = 'Bernhard';
        $author->setLastName('');

        $this->assertEquals($author, $form->getData());
    }

    public function testBindWithEmptyDataCreatesArrayIfDataClassIsNull()
    {
        $form = $this->factory->create('form', null, array(
            'data_class' => null,
            'required' => false,
        ));
        $form->add($this->factory->createNamed('firstName', 'text'));

        $form->setData(null);
        $form->bind(array('firstName' => 'Bernhard'));

        $this->assertSame(array('firstName' => 'Bernhard'), $form->getData());
    }

    public function testBindEmptyWithEmptyDataCreatesNoObjectIfNotRequired()
    {
        $form = $this->factory->create('form', null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => false,
        ));
        $form->add($this->factory->createNamed('firstName', 'text'));
        $form->add($this->factory->createNamed('lastName', 'text'));

        $form->setData(null);
        $form->bind(array('firstName' => '', 'lastName' => ''));

        $this->assertNull($form->getData());
    }

    public function testBindEmptyWithEmptyDataCreatesObjectIfRequired()
    {
        $form = $this->factory->create('form', null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => true,
        ));
        $form->add($this->factory->createNamed('firstName', 'text'));
        $form->add($this->factory->createNamed('lastName', 'text'));

        $form->setData(null);
        $form->bind(array('firstName' => '', 'lastName' => ''));

        $this->assertEquals(new Author(), $form->getData());
    }

    /*
     * We need something to write the field values into
     */
    public function testBindWithEmptyDataStoresArrayIfNoClassAvailable()
    {
        $form = $this->factory->create('form');
        $form->add($this->factory->createNamed('firstName', 'text'));

        $form->setData(null);
        $form->bind(array('firstName' => 'Bernhard'));

        $this->assertSame(array('firstName' => 'Bernhard'), $form->getData());
    }

    public function testBindWithEmptyDataPassesEmptyStringToTransformerIfNotCompound()
    {
        $form = $this->factory->createBuilder('form')
            ->addViewTransformer(new FixedDataTransformer(array(
                // required for the initial, internal setData(null)
                null => 'null',
                // required to test that bind(null) is converted to ''
                'empty' => '',
            )))
            ->setCompound(false)
            ->getForm();

        $form->bind(null);

        $this->assertSame('empty', $form->getData());
    }

    public function testBindWithEmptyDataUsesEmptyDataOption()
    {
        $author = new Author();

        $form = $this->factory->create('form', null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'empty_data' => $author,
        ));
        $form->add($this->factory->createNamed('firstName', 'text'));

        $form->bind(array('firstName' => 'Bernhard'));

        $this->assertSame($author, $form->getData());
        $this->assertEquals('Bernhard', $author->firstName);
    }

    public function provideZeros()
    {
        return array(
            array(0, '0'),
            array('0', '0'),
            array('00000', '00000'),
        );
    }

    /**
     * @dataProvider provideZeros
     * @see https://github.com/symfony/symfony/issues/1986
     */
    public function testSetDataThroughParamsWithZero($data, $dataAsString)
    {
        $form = $this->factory->create('form', null, array(
            'data' => $data,
            'compound' => false,
        ));
        $view = $form->createView();

        $this->assertFalse($form->isEmpty());

        $this->assertSame($dataAsString, $view->vars['value']);
        $this->assertSame($dataAsString, $form->getData());
    }

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testAttributesException()
    {
        $this->factory->create('form', null, array('attr' => ''));
    }

    public function testNameCanBeEmptyString()
    {
        $form = $this->factory->createNamed('', 'form');

        $this->assertEquals('', $form->getName());
    }
    public function testSubformDoesntCallSetters()
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder('form', $author);
        $builder->add('reference', 'form', array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
        ));
        $builder->get('reference')->add('firstName', 'text');
        $form = $builder->getForm();

        $form->bind(array(
            // reference has a getter, but not setter
            'reference' => array(
                'firstName' => 'Foo',
            )
        ));

        $this->assertEquals('Foo', $author->getReference()->firstName);
    }

    public function testSubformCallsSettersIfTheObjectChanged()
    {
        // no reference
        $author = new FormTest_AuthorWithoutRefSetter(null);
        $newReference = new Author();

        $builder = $this->factory->createBuilder('form', $author);
        $builder->add('referenceCopy', 'form', array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
        ));
        $builder->get('referenceCopy')->add('firstName', 'text');
        $form = $builder->getForm();

        $form['referenceCopy']->setData($newReference); // new author object

        $form->bind(array(
        // referenceCopy has a getter that returns a copy
            'referenceCopy' => array(
                'firstName' => 'Foo',
        )
        ));

        $this->assertEquals('Foo', $author->getReferenceCopy()->firstName);
    }

    public function testSubformCallsSettersIfByReferenceIsFalse()
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder('form', $author);
        $builder->add('referenceCopy', 'form', array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'by_reference' => false
        ));
        $builder->get('referenceCopy')->add('firstName', 'text');
        $form = $builder->getForm();

        $form->bind(array(
            // referenceCopy has a getter that returns a copy
            'referenceCopy' => array(
                'firstName' => 'Foo',
            )
        ));

        // firstName can only be updated if setReferenceCopy() was called
        $this->assertEquals('Foo', $author->getReferenceCopy()->firstName);
    }

    public function testSubformCallsSettersIfReferenceIsScalar()
    {
        $author = new FormTest_AuthorWithoutRefSetter('scalar');

        $builder = $this->factory->createBuilder('form', $author);
        $builder->add('referenceCopy', 'form');
        $builder->get('referenceCopy')->addViewTransformer(new CallbackTransformer(
            function () {},
            function ($value) { // reverseTransform
                return 'foobar';
            }
        ));
        $form = $builder->getForm();

        $form->bind(array(
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

        $builder = $this->factory->createBuilder('form');
        $builder->setData($author);
        $builder->add('referenceCopy', 'form');
        $builder->get('referenceCopy')->addViewTransformer(new CallbackTransformer(
            function () {},
            function ($value) use ($ref2) { // reverseTransform
                return $ref2;
            }
        ));
        $form = $builder->getForm();

        $form->bind(array(
            'referenceCopy' => array('a' => 'b'), // doesn't matter actually
        ));

        // the new reference was inserted into the array
        $author = $form->getData();
        $this->assertSame($ref2, $author['referenceCopy']);
    }

    public function testPassMultipartFalseToView()
    {
        $form = $this->factory->create('form');
        $view = $form->createView();

        $this->assertFalse($view->vars['multipart']);
    }

    public function testPassMultipartTrueIfAnyChildIsMultipartToView()
    {
        $form = $this->factory->create('form');
        $form->add($this->factory->create('text'));
        $form->add($this->factory->create('file'));
        $view = $form->createView();

        $this->assertTrue($view->vars['multipart']);
    }

    public function testCreateViewDoNoMarkItAsRendered()
    {
        $form = $this->factory->create('form');
        $form->add($this->factory->create('form'));
        $view = $form->createView();

        $this->assertFalse($view->isRendered());
    }

    public function testErrorBubblingIfCompound()
    {
        $form = $this->factory->create('form', null, array(
            'compound' => true,
        ));

        $this->assertTrue($form->getConfig()->getErrorBubbling());
    }

    public function testNoErrorBubblingIfNotCompound()
    {
        $form = $this->factory->create('form', null, array(
            'compound' => false,
        ));

        $this->assertFalse($form->getConfig()->getErrorBubbling());
    }

    public function testOverrideErrorBubbling()
    {
        $form = $this->factory->create('form', null, array(
            'compound' => false,
            'error_bubbling' => true,
        ));

        $this->assertTrue($form->getConfig()->getErrorBubbling());
    }

    public function testPropertyPath()
    {
        $form = $this->factory->create('form', null, array(
            'property_path' => 'foo',
        ));

        $this->assertEquals(new PropertyPath('foo'), $form->getPropertyPath());
        $this->assertTrue($form->getConfig()->getMapped());
    }

    public function testPropertyPathNullImpliesDefault()
    {
        $form = $this->factory->createNamed('name', 'form', null, array(
            'property_path' => null,
        ));

        $this->assertEquals(new PropertyPath('name'), $form->getPropertyPath());
        $this->assertTrue($form->getConfig()->getMapped());
    }

    // BC
    public function testPropertyPathFalseImpliesDefaultNotMapped()
    {
        $form = $this->factory->createNamed('name', 'form', null, array(
            'property_path' => false,
        ));

        $this->assertEquals(new PropertyPath('name'), $form->getPropertyPath());
        $this->assertFalse($form->getConfig()->getMapped());
    }

    public function testNotMapped()
    {
        $form = $this->factory->create('form', null, array(
            'property_path' => 'foo',
            'mapped' => false,
        ));

        $this->assertEquals(new PropertyPath('foo'), $form->getPropertyPath());
        $this->assertFalse($form->getConfig()->getMapped());
    }

    public function testViewValidUnbound()
    {
        $form = $this->factory->create('form');
        $view = $form->createView();
        $this->assertTrue($view->vars['valid']);
    }

    public function testViewNotValidBound()
    {
        $form = $this->factory->create('form');
        $form->bind(array());
        $form->addError(new FormError('An error'));
        $view = $form->createView();
        $this->assertFalse($view->vars['valid']);
    }

    public function testDataOptionSupersedesSetDataCalls()
    {
        $form = $this->factory->create('form', null, array(
            'data' => 'default',
            'compound' => false,
        ));

        $form->setData('foobar');

        $this->assertSame('default', $form->getData());
    }

    public function testNormDataIsPassedToView()
    {
        $view = $this->factory->createBuilder('form')
            ->addViewTransformer(new FixedDataTransformer(array(
                'foo' => 'bar',
            )))
            ->setData('foo')
            ->getForm()
            ->createView();

        $this->assertSame('foo', $view->vars['data']);
        $this->assertSame('bar', $view->vars['value']);
    }

    // https://github.com/symfony/symfony/issues/6862
    public function testPassZeroLabelToView()
    {
        $view = $this->factory->create('form', null, array(
                'label' => '0'
            ))
            ->createView();

        $this->assertSame('0', $view->vars['label']);
    }
}
