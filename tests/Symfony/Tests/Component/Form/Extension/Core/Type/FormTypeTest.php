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

use Symfony\Component\Form\Form;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Tests\Component\Form\Fixtures\Author;

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
    public function testSubformDoesntCallSetters()
    {
        $author = new FormTest_AuthorWithoutRefSetter(new Author());

        $builder = $this->factory->createBuilder('form');
        $builder->add('reference', 'form');
        $builder->get('reference')->add('firstName', 'field');
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
        $builder->get('referenceCopy')->add('firstName', 'field');
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
        $builder->get('referenceCopy')->add('firstName', 'field');
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
}
