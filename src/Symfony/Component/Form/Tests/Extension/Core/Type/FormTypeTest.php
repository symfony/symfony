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
    public function testGetPropertyPathDefaultPath()
    {
        $form = $this->factory->createNamed('form', 'title');

        $this->assertEquals(new PropertyPath('title'), $form->getAttribute('property_path'));
    }

    public function testGetPropertyPathPathIsZero()
    {
        $form = $this->factory->create('form', null, array('property_path' => '0'));

        $this->assertEquals(new PropertyPath('0'), $form->getAttribute('property_path'));
    }

    public function testGetPropertyPathPathIsEmpty()
    {
        $form = $this->factory->create('form', null, array('property_path' => ''));

        $this->assertNull($form->getAttribute('property_path'));
    }

    public function testGetPropertyPathPathIsFalse()
    {
        $form = $this->factory->create('form', null, array('property_path' => false));

        $this->assertNull($form->getAttribute('property_path'));
    }

    public function testGetPropertyPathPathIsNull()
    {
        $form = $this->factory->createNamed('form', 'title', null, array('property_path' => null));

        $this->assertEquals(new PropertyPath('title'), $form->getAttribute('property_path'));
    }

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
            ->appendClientTransformer(new FixedDataTransformer(array(
                null => '',
                'reverse[a]' => 'a',
            )))
            ->getForm();

        $form->bind(' a ');

        $this->assertEquals('a', $form->getClientData());
        $this->assertEquals('reverse[a]', $form->getData());
    }

    public function testBoundDataIsNotTrimmedBeforeTransformingIfNoTrimming()
    {
        $form = $this->factory->createBuilder('form', null, array('trim' => false))
            ->appendClientTransformer(new FixedDataTransformer(array(
                null => '',
                'reverse[ a ]' => ' a ',
            )))
            ->getForm();

        $form->bind(' a ');

        $this->assertEquals(' a ', $form->getClientData());
        $this->assertEquals('reverse[ a ]', $form->getData());
    }

    public function testPassIdAndNameToView()
    {
        $form = $this->factory->createNamed('form', 'name');
        $view = $form->createView();

        $this->assertEquals('name', $view->get('id'));
        $this->assertEquals('name', $view->get('name'));
        $this->assertEquals('name', $view->get('full_name'));
    }

    public function testStripLeadingUnderscoresAndDigitsFromId()
    {
        $form = $this->factory->createNamed('form', '_09name');
        $view = $form->createView();

        $this->assertEquals('name', $view->get('id'));
        $this->assertEquals('_09name', $view->get('name'));
        $this->assertEquals('_09name', $view->get('full_name'));
    }

    public function testPassIdAndNameToViewWithParent()
    {
        $parent = $this->factory->createNamed('form', 'parent');
        $parent->add($this->factory->createNamed('form', 'child'));
        $view = $parent->createView();

        $this->assertEquals('parent_child', $view['child']->get('id'));
        $this->assertEquals('child', $view['child']->get('name'));
        $this->assertEquals('parent[child]', $view['child']->get('full_name'));
    }

    public function testPassIdAndNameToViewWithGrandParent()
    {
        $parent = $this->factory->createNamed('form', 'parent');
        $parent->add($this->factory->createNamed('form', 'child'));
        $parent['child']->add($this->factory->createNamed('form', 'grand_child'));
        $view = $parent->createView();

        $this->assertEquals('parent_child_grand_child', $view['child']['grand_child']->get('id'));
        $this->assertEquals('grand_child', $view['child']['grand_child']->get('name'));
        $this->assertEquals('parent[child][grand_child]', $view['child']['grand_child']->get('full_name'));
    }

    public function testNonReadOnlyFormWithReadOnlyParentBeingReadOnly()
    {
        $parent = $this->factory->createNamed('form', 'parent', null, array('read_only' => true));
        $child  = $this->factory->createNamed('form', 'child');
        $view   = $parent->add($child)->createView();

        $this->assertTrue($view['child']->get('read_only'));
    }

    public function testReadOnlyFormWithNonReadOnlyParentBeingReadOnly()
    {
        $parent = $this->factory->createNamed('form', 'parent');
        $child  = $this->factory->createNamed('form', 'child', null, array('read_only' => true));
        $view   = $parent->add($child)->createView();

        $this->assertTrue($view['child']->get('read_only'));
    }

    public function testNonReadOnlyFormWithNonReadOnlyParentBeingNonReadOnly()
    {
        $parent = $this->factory->createNamed('form', 'parent');
        $child  = $this->factory->createNamed('form', 'child');
        $view   = $parent->add($child)->createView();

        $this->assertFalse($view['child']->get('read_only'));
    }

    public function testPassMaxLengthToView()
    {
        $form = $this->factory->create('form', null, array('max_length' => 10));
        $view = $form->createView();

        $this->assertSame(10, $view->get('max_length'));
    }

    public function testPassTranslationDomainToView()
    {
        $form = $this->factory->create('form', null, array('translation_domain' => 'test'));
        $view = $form->createView();

        $this->assertSame('test', $view->get('translation_domain'));
    }

    public function testPassDefaultLabelToView()
    {
        $form = $this->factory->createNamed('form', '__test___field');
        $view = $form->createView();

        $this->assertSame('Test field', $view->get('label'));
    }

    public function testPassLabelToView()
    {
        $form = $this->factory->createNamed('form', '__test___field', null, array('label' => 'My label'));
        $view = $form->createView();

        $this->assertSame('My label', $view->get('label'));
    }

    public function testDefaultTranslationDomain()
    {
        $form = $this->factory->create('form');
        $view = $form->createView();

        $this->assertSame('messages', $view->get('translation_domain'));
    }

    public function testBindWithEmptyDataCreatesObjectIfClassAvailable()
    {
        $form = $this->factory->create('form', null, array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'required' => false,
        ));
        $form->add($this->factory->createNamed('form', 'firstName'));
        $form->add($this->factory->createNamed('form', 'lastName'));

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
        $form->add($this->factory->createNamed('form', 'firstName'));
        $form->add($this->factory->createNamed('form', 'lastName'));

        $form->setData(null);
        // partially empty, still an object is created
        $form->bind(array('firstName' => 'Bernhard', 'lastName' => ''));

        $author = new Author();
        $author->firstName = 'Bernhard';
        $author->setLastName('');

        $this->assertEquals($author, $form->getData());
    }

    public function testBindWithEmptyDataDoesNotCreateObjectIfDataClassIsNull()
    {
        $form = $this->factory->create('form', null, array(
            'data' => new Author(),
            'data_class' => null,
            'required' => false,
        ));
        $form->add($this->factory->createNamed('form', 'firstName'));

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
        $form->add($this->factory->createNamed('form', 'firstName'));
        $form->add($this->factory->createNamed('form', 'lastName'));

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
        $form->add($this->factory->createNamed('form', 'firstName'));
        $form->add($this->factory->createNamed('form', 'lastName'));

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
        $form->add($this->factory->createNamed('form', 'firstName'));

        $form->setData(null);
        $form->bind(array('firstName' => 'Bernhard'));

        $this->assertSame(array('firstName' => 'Bernhard'), $form->getData());
    }

    public function testBindWithEmptyDataPassesEmptyStringToTransformerIfNoChildren()
    {
        $form = $this->factory->createBuilder('form')
            ->appendClientTransformer(new FixedDataTransformer(array(
                // required for the initial, internal setData(null)
                null => 'null',
                // required to test that bind(null) is converted to ''
                'empty' => '',
            )))
            ->getForm();

        $form->bind(null);

        $this->assertSame('empty', $form->getData());
    }

    public function testBindWithEmptyDataUsesEmptyDataOption()
    {
        $author = new Author();

        $form = $this->factory->create('form', null, array(
            'empty_data' => $author,
        ));
        $form->add($this->factory->createNamed('form', 'firstName'));

        $form->bind(array('firstName' => 'Bernhard'));

        $this->assertSame($author, $form->getData());
        $this->assertEquals('Bernhard', $author->firstName);
    }

    public function testGetAttributesIsEmpty()
    {
        $form = $this->factory->create('form', null, array('attr' => array()));

        $this->assertCount(0, $form->getAttribute('attr'));
    }

    /**
     * @see https://github.com/symfony/symfony/issues/1986
     */
    public function testSetDataThroughParamsWithZero()
    {
        $form = $this->factory->create('form', null, array('data' => 0));
        $view = $form->createView();

        $this->assertFalse($form->isEmpty());

        $this->assertSame('0', $view->get('value'));
        $this->assertSame('0', $form->getData());

        $form = $this->factory->create('form', null, array('data' => '0'));
        $view = $form->createView();

        $this->assertFalse($form->isEmpty());

        $this->assertSame('0', $view->get('value'));
        $this->assertSame('0', $form->getData());

        $form = $this->factory->create('form', null, array('data' => '00000'));
        $view = $form->createView();

        $this->assertFalse($form->isEmpty());

        $this->assertSame('00000', $view->get('value'));
        $this->assertSame('00000', $form->getData());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testAttributesException()
    {
        $form = $this->factory->create('form', null, array('attr' => ''));
    }

    public function testNameCanBeEmptyString()
    {
        $form = $this->factory->createNamed('form', '');

        $this->assertEquals('', $form->getName());
    }
    public function testSubformDoesntCallSetters()
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder('form');
        $builder->add('reference', 'form');
        $builder->get('reference')->add('firstName', 'form');
        $builder->setData($author);
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

        $builder = $this->factory->createBuilder('form');
        $builder->add('referenceCopy', 'form');
        $builder->get('referenceCopy')->add('firstName', 'form');
        $builder->setData($author);
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

        $builder = $this->factory->createBuilder('form');
        $builder->add('referenceCopy', 'form', array('by_reference' => false));
        $builder->get('referenceCopy')->add('firstName', 'form');
        $builder->setData($author);
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

        $builder = $this->factory->createBuilder('form');
        $builder->add('referenceCopy', 'form');
        $builder->get('referenceCopy')->appendClientTransformer(new CallbackTransformer(
        function () {},
        function ($value) { // reverseTransform

            return 'foobar';
        }
        ));
        $builder->setData($author);
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
        $builder->get('referenceCopy')->appendClientTransformer(new CallbackTransformer(
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

        $this->assertFalse($view->get('multipart'));
    }

    public function testPassMultipartTrueIfAnyChildIsMultipartToView()
    {
        $form = $this->factory->create('form');
        $form->add($this->factory->create('text'));
        $form->add($this->factory->create('file'));
        $view = $form->createView();

        $this->assertTrue($view->get('multipart'));
    }

    public function testCreateViewDoNoMarkItAsRendered()
    {
        $form = $this->factory->create('form');
        $form->add($this->factory->create('form'));
        $view = $form->createView();

        $this->assertFalse($view->isRendered());
    }

    public function testErrorBubblingIfNoSingleControl()
    {
        $form = $this->factory->create('form', null, array(
            'single_control' => false,
        ));

        $this->assertTrue($form->getErrorBubbling());
    }

    public function testNoErrorBubblingIfSingleControl()
    {
        $form = $this->factory->create('form', null, array(
            'single_control' => true,
        ));

        $this->assertFalse($form->getErrorBubbling());
    }

    public function testOverrideErrorBubbling()
    {
        $form = $this->factory->create('form', null, array(
            'single_control' => true,
            'error_bubbling' => true,
        ));

        $this->assertTrue($form->getErrorBubbling());
    }
}
