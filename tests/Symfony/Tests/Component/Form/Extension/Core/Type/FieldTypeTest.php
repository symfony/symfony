<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\Type;

require_once __DIR__ . '/TypeTestCase.php';
require_once __DIR__ . '/../../../Fixtures/Author.php';
require_once __DIR__ . '/../../../Fixtures/FixedDataTransformer.php';
require_once __DIR__ . '/../../../Fixtures/FixedFilterListener.php';

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Form;
use Symfony\Tests\Component\Form\Fixtures\Author;
use Symfony\Tests\Component\Form\Fixtures\FixedDataTransformer;
use Symfony\Tests\Component\Form\Fixtures\FixedFilterListener;

class FieldTypeTest extends TypeTestCase
{
    public function testGetPropertyPathDefaultPath()
    {
        $form = $this->factory->createNamed('field', 'title');

        $this->assertEquals(new PropertyPath('title'), $form->getAttribute('property_path'));
    }

    public function testGetPropertyPathPathIsZero()
    {
        $form = $this->factory->create('field', null, array('property_path' => '0'));

        $this->assertEquals(new PropertyPath('0'), $form->getAttribute('property_path'));
    }

    public function testGetPropertyPathPathIsEmpty()
    {
        $form = $this->factory->create('field', null, array('property_path' => ''));

        $this->assertNull($form->getAttribute('property_path'));
    }

    public function testGetPropertyPathPathIsFalse()
    {
        $form = $this->factory->create('field', null, array('property_path' => false));

        $this->assertNull($form->getAttribute('property_path'));
    }

    public function testGetPropertyPathPathIsNull()
    {
        $form = $this->factory->createNamed('field', 'title', null, array('property_path' => null));

        $this->assertEquals(new PropertyPath('title'), $form->getAttribute('property_path'));
    }

    public function testPassRequiredAsOption()
    {
        $form = $this->factory->create('field', null, array('required' => false));

        $this->assertFalse($form->isRequired());

        $form = $this->factory->create('field', null, array('required' => true));

        $this->assertTrue($form->isRequired());
    }

    public function testPassReadOnlyAsOption()
    {
        $form = $this->factory->create('field', null, array('read_only' => true));

        $this->assertTrue($form->isReadOnly());
    }

    public function testBoundDataIsTrimmedBeforeTransforming()
    {
        $form = $this->factory->createBuilder('field')
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
        $form = $this->factory->createBuilder('field', null, array('trim' => false))
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
        $form = $this->factory->createNamed('field', 'name');
        $view = $form->createView();

        $this->assertEquals('name', $view->get('id'));
        $this->assertEquals('name', $view->get('name'));
        $this->assertEquals('name', $view->get('full_name'));
    }

    public function testPassIdAndNameToViewWithParent()
    {
        $parent = $this->factory->createNamed('field', 'parent');
        $parent->add($this->factory->createNamed('field', 'child'));
        $view = $parent->createView();

        $this->assertEquals('parent_child', $view['child']->get('id'));
        $this->assertEquals('child', $view['child']->get('name'));
        $this->assertEquals('parent[child]', $view['child']->get('full_name'));
    }

    public function testPassIdAndNameToViewWithGrandParent()
    {
        $parent = $this->factory->createNamed('field', 'parent');
        $parent->add($this->factory->createNamed('field', 'child'));
        $parent['child']->add($this->factory->createNamed('field', 'grand_child'));
        $view = $parent->createView();

        $this->assertEquals('parent_child_grand_child', $view['child']['grand_child']->get('id'));
        $this->assertEquals('grand_child', $view['child']['grand_child']->get('name'));
        $this->assertEquals('parent[child][grand_child]', $view['child']['grand_child']->get('full_name'));
    }

    public function testPassMaxLengthToView()
    {
        $form = $this->factory->create('field', null, array('max_length' => 10));
        $view = $form->createView();

        $this->assertSame(10, $view->get('max_length'));
    }

    public function testBindWithEmptyDataCreatesObjectIfClassAvailable()
    {
        $form = $this->factory->create('form', null, array(
            'data_class' => 'Symfony\Tests\Component\Form\Fixtures\Author',
        ));
        $form->add($this->factory->createNamed('field', 'firstName'));

        $form->setData(null);
        $form->bind(array('firstName' => 'Bernhard'));

        $author = new Author();
        $author->firstName = 'Bernhard';

        $this->assertEquals($author, $form->getData());
    }

    /*
     * We need something to write the field values into
     */
    public function testBindWithEmptyDataStoresArrayIfNoClassAvailable()
    {
        $form = $this->factory->create('form');
        $form->add($this->factory->createNamed('field', 'firstName'));

        $form->setData(null);
        $form->bind(array('firstName' => 'Bernhard'));

        $this->assertSame(array('firstName' => 'Bernhard'), $form->getData());
    }

    public function testBindWithEmptyDataUsesEmptyDataOption()
    {
        $author = new Author();

        $form = $this->factory->create('form', null, array(
            'empty_data' => $author,
        ));
        $form->add($this->factory->createNamed('field', 'firstName'));

        $form->bind(array('firstName' => 'Bernhard'));

        $this->assertSame($author, $form->getData());
        $this->assertEquals('Bernhard', $author->firstName);
    }

    public function testGetAttributesIsEmpty()
    {
        $form = $this->factory->create('field', null, array('attr' => array()));

        $this->assertEquals(0, count($form->getAttribute('attr')));
    }

    /**
     * @see https://github.com/symfony/symfony/issues/1986
     */
    public function testSetDataThroughParamsWithZero()
    {
        $form = $this->factory->create('field', null, array('data' => 0));
        $view = $form->createView();

        $this->assertFalse($form->isEmpty());

        $this->assertSame('0', $view->get('value'));
        $this->assertSame('0', $form->getData());

        $form = $this->factory->create('field', null, array('data' => '0'));
        $view = $form->createView();

        $this->assertFalse($form->isEmpty());

        $this->assertSame('0', $view->get('value'));
        $this->assertSame('0', $form->getData());

        $form = $this->factory->create('field', null, array('data' => '00000'));
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
        $form = $this->factory->create('field', null, array('attr' => ''));
    }

    // https://github.com/symfony/symfony/issues/6862
    public function testPassZeroLabelToView()
    {
        $view = $this->factory->create('field', null, array('label' => 0))->createView();

        $this->assertEquals('0', $view->get('label'));
    }

}
