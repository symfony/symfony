<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__.'/Fixtures/FixedDataTransformer.php';

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Tests\Component\Form\Fixtures\FixedDataTransformer;

class FormTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $builder;

    private $form;

    protected function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->form = $this->getBuilder()->getForm();
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConstructExpectsValidValidators()
    {
        $validators = array(new \stdClass());

        new Form('name', $this->dispatcher, array(), null, null, null, $validators);
    }

    public function testDataIsInitializedEmpty()
    {
        $norm = new FixedDataTransformer(array(
            '' => 'foo',
        ));
        $client = new FixedDataTransformer(array(
            'foo' => 'bar',
        ));

        $form = new Form('name', $this->dispatcher, array(), $client, $norm);

        $this->assertNull($form->getData());
        $this->assertSame('foo', $form->getNormData());
        $this->assertSame('bar', $form->getClientData());
    }

    public function testErrorsBubbleUpIfEnabled()
    {
        $error = new FormError('Error!');
        $parent = $this->form;
        $form = $this->getBuilder()->setErrorBubbling(true)->getForm();

        $form->setParent($parent);
        $form->addError($error);

        $this->assertEquals(array(), $form->getErrors());
        $this->assertEquals(array($error), $parent->getErrors());
    }

    public function testErrorsDontBubbleUpIfDisabled()
    {
        $error = new FormError('Error!');
        $parent = $this->form;
        $form = $this->getBuilder()->setErrorBubbling(false)->getForm();

        $form->setParent($parent);
        $form->addError($error);

        $this->assertEquals(array($error), $form->getErrors());
        $this->assertEquals(array(), $parent->getErrors());
    }

    public function testValidIfAllChildrenAreValid()
    {
        $this->form->add($this->getValidForm('firstName'));
        $this->form->add($this->getValidForm('lastName'));

        $this->form->bind(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));

        $this->assertTrue($this->form->isValid());
    }

    public function testInvalidIfChildrenIsInvalid()
    {
        $this->form->add($this->getValidForm('firstName'));
        $this->form->add($this->getInvalidForm('lastName'));

        $this->form->bind(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));

        $this->assertFalse($this->form->isValid());
    }

    public function testBind()
    {
        $child = $this->getMockForm('firstName');

        $this->form->add($child);

        $child->expects($this->once())
            ->method('bind')
            ->with($this->equalTo('Bernhard'));

        $this->form->bind(array('firstName' => 'Bernhard'));

        $this->assertEquals(array('firstName' => 'Bernhard'), $this->form->getData());
    }

    public function testBindForwardsNullIfValueIsMissing()
    {
        $child = $this->getMockForm('firstName');

        $this->form->add($child);

        $child->expects($this->once())
            ->method('bind')
            ->with($this->equalTo(null));

        $this->form->bind(array());
    }

    public function testNeverRequiredIfParentNotRequired()
    {
        $parent = $this->getBuilder()->setRequired(false)->getForm();
        $child = $this->getBuilder()->setRequired(true)->getForm();

        $child->setParent($parent);

        $this->assertFalse($child->isRequired());
    }

    public function testRequired()
    {
        $parent = $this->getBuilder()->setRequired(true)->getForm();
        $child = $this->getBuilder()->setRequired(true)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isRequired());
    }

    public function testNotRequired()
    {
        $parent = $this->getBuilder()->setRequired(true)->getForm();
        $child = $this->getBuilder()->setRequired(false)->getForm();

        $child->setParent($parent);

        $this->assertFalse($child->isRequired());
    }

    public function testAlwaysReadOnlyIfParentReadOnly()
    {
        $parent = $this->getBuilder()->setReadOnly(true)->getForm();
        $child = $this->getBuilder()->setReadOnly(false)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isReadOnly());
    }

    public function testReadOnly()
    {
        $parent = $this->getBuilder()->setReadOnly(false)->getForm();
        $child = $this->getBuilder()->setReadOnly(true)->getForm();

        $child->setParent($parent);

        $this->assertTrue($child->isReadOnly());
    }

    public function testNotReadOnly()
    {
        $parent = $this->getBuilder()->setReadOnly(false)->getForm();
        $child = $this->getBuilder()->setReadOnly(false)->getForm();

        $child->setParent($parent);

        $this->assertFalse($child->isReadOnly());
    }

    public function testCloneChildren()
    {
        $child = $this->getBuilder('child')->getForm();
        $this->form->add($child);

        $clone = clone $this->form;

        $this->assertNotSame($this->form, $clone);
        $this->assertNotSame($child, $clone['child']);
    }

    public function testGetRootReturnsRootOfParent()
    {
        $parent = $this->getMockForm();
        $parent->expects($this->once())
            ->method('getRoot')
            ->will($this->returnValue('ROOT'));

        $this->form->setParent($parent);

        $this->assertEquals('ROOT', $this->form->getRoot());
    }

    public function testGetRootReturnsSelfIfNoParent()
    {
        $this->assertSame($this->form, $this->form->getRoot());
    }

    public function testIsEmptyIfEmptyArray()
    {
        $this->form->setData(array());

        $this->assertTrue($this->form->isEmpty());
    }

    public function testIsEmptyIfNull()
    {
        $this->form->setData(null);

        $this->assertTrue($this->form->isEmpty());
    }

    public function testIsEmptyIfEmptyString()
    {
        $this->form->setData('');

        $this->assertTrue($this->form->isEmpty());
    }

    public function testIsNotEmptyIfText()
    {
        $this->form->setData('foobar');

        $this->assertFalse($this->form->isEmpty());
    }

    public function testIsNotEmptyIfChildNotEmpty()
    {
        $child = $this->getMockForm();
        $child->expects($this->once())
            ->method('isEmpty')
            ->will($this->returnValue(false));

        $this->form->setData(null);
        $this->form->add($child);

        $this->assertFalse($this->form->isEmpty());
    }

    public function testValidIfBound()
    {
        $this->form->bind('foobar');

        $this->assertTrue($this->form->isValid());
    }

    public function testNotValidIfNotBound()
    {
        $this->assertFalse($this->form->isValid());
    }

    public function testNotValidIfErrors()
    {
        $this->form->bind('foobar');
        $this->form->addError(new FormError('Error!'));

        $this->assertFalse($this->form->isValid());
    }

    public function testNotValidIfChildNotValid()
    {
        $child = $this->getMockForm();
        $child->expects($this->once())
            ->method('isValid')
            ->will($this->returnValue(false));

        $this->form->bind('foobar');
        $this->form->add($child);

        $this->assertFalse($this->form->isValid());
    }

    public function testHasErrors()
    {
        $this->form->addError(new FormError('Error!'));

        $this->assertTrue($this->form->hasErrors());
    }

    public function testHasNoErrors()
    {
        $this->assertFalse($this->form->hasErrors());
    }

    public function testHasChildren()
    {
        $this->form->add($this->getBuilder()->getForm());

        $this->assertTrue($this->form->hasChildren());
    }

    public function testHasNoChildren()
    {
        $this->assertFalse($this->form->hasChildren());
    }

    public function testAdd()
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);

        $this->assertSame($this->form, $child->getParent());
        $this->assertSame(array('foo' => $child), $this->form->getChildren());
    }

    public function testRemove()
    {
        $child = $this->getBuilder('foo')->getForm();
        $this->form->add($child);
        $this->form->remove('foo');

        $this->assertNull($child->getParent());
        $this->assertFalse($this->form->hasChildren());
    }

    public function testRemoveIgnoresUnknownName()
    {
        $this->form->remove('notexisting');
    }

    public function testArrayAccess()
    {
        $child = $this->getBuilder('foo')->getForm();

        $this->form[] = $child;

        $this->assertTrue(isset($this->form['foo']));
        $this->assertSame($child, $this->form['foo']);

        unset($this->form['foo']);

        $this->assertFalse(isset($this->form['foo']));
    }

    public function testCountable()
    {
        $this->form->add($this->getBuilder('foo')->getForm());
        $this->form->add($this->getBuilder('bar')->getForm());

        $this->assertEquals(2, count($this->form));
    }

    public function testIterator()
    {
        $this->form->add($this->getBuilder('foo')->getForm());
        $this->form->add($this->getBuilder('bar')->getForm());

        $this->assertSame($this->form->getChildren(), iterator_to_array($this->form));
    }

    public function testIsBound()
    {
        $this->form->bind('foobar');

        $this->assertTrue($this->form->isBound());
    }

    public function testIsNotBound()
    {
        $this->assertFalse($this->form->isBound());
    }

    protected function getBuilder($name = 'name')
    {
        return new FormBuilder($name, $this->dispatcher);
    }

    protected function getMockForm($name = 'name')
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');

        $form->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));

        return $form;
    }

    protected function getValidForm($name)
    {
        $form = $this->getMockForm($name);

        $form->expects($this->any())
            ->method('isValid')
            ->will($this->returnValue(true));

        return $form;
    }

    protected function getInvalidForm($name)
    {
        $form = $this->getMockForm($name);

        $form->expects($this->any())
            ->method('isValid')
            ->will($this->returnValue(false));

        return $form;
    }
}