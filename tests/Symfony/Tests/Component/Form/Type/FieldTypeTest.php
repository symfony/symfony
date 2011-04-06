<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Type;

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../Fixtures/Author.php';
require_once __DIR__ . '/../Fixtures/FixedDataTransformer.php';
require_once __DIR__ . '/../Fixtures/FixedFilterListener.php';

use Symfony\Component\Form\DataTransformer\DataTransformerInterface;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\DataTransformer\TransformationFailedException;
use Symfony\Tests\Component\Form\Fixtures\Author;
use Symfony\Tests\Component\Form\Fixtures\FixedDataTransformer;
use Symfony\Tests\Component\Form\Fixtures\FixedFilterListener;

class FieldTypeTest extends TestCase
{
    public function testGetPropertyPathDefaultPath()
    {
        $form = $this->factory->create('field', 'title');

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
        $form = $this->factory->create('field', 'title', array('property_path' => null));

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

    public function testPassIdAndNameToRenderer()
    {
        $form = $this->factory->create('field', 'name');
        $renderer = $this->factory->createRenderer($form, 'stub');

        $this->assertEquals('name', $renderer->getVar('id'));
        $this->assertEquals('name', $renderer->getVar('name'));
    }

    public function testPassIdAndNameToRendererWithParent()
    {
        $parent = $this->factory->create('field', 'parent');
        $parent->add($this->factory->create('field', 'child'));
        $renderer = $this->factory->createRenderer($parent, 'stub');

        $this->assertEquals('parent_child', $renderer['child']->getVar('id'));
        $this->assertEquals('parent[child]', $renderer['child']->getVar('name'));
    }

    public function testPassIdAndNameToRendererWithGrandParent()
    {
        $parent = $this->factory->create('field', 'parent');
        $parent->add($this->factory->create('field', 'child'));
        $parent['child']->add($this->factory->create('field', 'grand_child'));
        $renderer = $this->factory->createRenderer($parent, 'stub');

        $this->assertEquals('parent_child_grand_child', $renderer['child']['grand_child']->getVar('id'));
        $this->assertEquals('parent[child][grand_child]', $renderer['child']['grand_child']->getVar('name'));
    }

    public function testPassMaxLengthToRenderer()
    {
        $form = $this->factory->create('field', null, array('max_length' => 10));
        $renderer = $this->factory->createRenderer($form, 'stub');

        $this->assertSame(10, $renderer->getVar('max_length'));
    }

    public function testBindWithEmptyDataCreatesObjectIfClassAvailable()
    {
        $form = $this->factory->create('form', 'author', array(
            'data_class' => 'Symfony\Tests\Component\Form\Fixtures\Author',
        ));
        $form->add($this->factory->create('field', 'firstName'));

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
        $form = $this->factory->create('form', 'author');
        $form->add($this->factory->create('field', 'firstName'));

        $form->setData(null);
        $form->bind(array('firstName' => 'Bernhard'));

        $this->assertSame(array('firstName' => 'Bernhard'), $form->getData());
    }

    public function testBindWithEmptyDataUsesEmptyDataOption()
    {
        $author = new Author();

        $form = $this->factory->create('form', 'author', array(
            'empty_data' => $author,
        ));
        $form->add($this->factory->create('field', 'firstName'));

        $form->bind(array('firstName' => 'Bernhard'));

        $this->assertSame($author, $form->getData());
        $this->assertEquals('Bernhard', $author->firstName);
    }
}
