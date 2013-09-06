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

class RepeatedTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    protected $form;

    protected function setUp()
    {
        parent::setUp();

        $this->form = $this->factory->create('repeated', null, array(
            'type' => 'text',
        ));
        $this->form->setData(null);
    }

    public function testSetData()
    {
        $this->form->setData('foobar');

        $this->assertEquals('foobar', $this->form['first']->getData());
        $this->assertEquals('foobar', $this->form['second']->getData());
    }

    public function testSetOptions()
    {
        $form = $this->factory->create('repeated', null, array(
            'type'    => 'text',
            'options' => array('label' => 'Global'),
        ));

        $this->assertEquals('Global', $form['first']->getConfig()->getOption('label'));
        $this->assertEquals('Global', $form['second']->getConfig()->getOption('label'));
        $this->assertTrue($form['first']->isRequired());
        $this->assertTrue($form['second']->isRequired());
    }

    public function testSetOptionsPerChild()
    {
        $form = $this->factory->create('repeated', null, array(
            // the global required value cannot be overridden
            'type'           => 'text',
            'first_options'  => array('label' => 'Test', 'required' => false),
            'second_options' => array('label' => 'Test2')
        ));

        $this->assertEquals('Test', $form['first']->getConfig()->getOption('label'));
        $this->assertEquals('Test2', $form['second']->getConfig()->getOption('label'));
        $this->assertTrue($form['first']->isRequired());
        $this->assertTrue($form['second']->isRequired());
    }

    public function testSetRequired()
    {
        $form = $this->factory->create('repeated', null, array(
            'required' => false,
            'type'     => 'text',
        ));

        $this->assertFalse($form['first']->isRequired());
        $this->assertFalse($form['second']->isRequired());
    }

    public function testSetErrorBubblingToTrue()
    {
        $form = $this->factory->create('repeated', null, array(
            'error_bubbling' => true,
        ));

        $this->assertTrue($form->getConfig()->getOption('error_bubbling'));
        $this->assertTrue($form['first']->getConfig()->getOption('error_bubbling'));
        $this->assertTrue($form['second']->getConfig()->getOption('error_bubbling'));
    }

    public function testSetErrorBubblingToFalse()
    {
        $form = $this->factory->create('repeated', null, array(
            'error_bubbling' => false,
        ));

        $this->assertFalse($form->getConfig()->getOption('error_bubbling'));
        $this->assertFalse($form['first']->getConfig()->getOption('error_bubbling'));
        $this->assertFalse($form['second']->getConfig()->getOption('error_bubbling'));
    }

    public function testSetErrorBubblingIndividually()
    {
        $form = $this->factory->create('repeated', null, array(
            'error_bubbling' => true,
            'options' => array('error_bubbling' => false),
            'second_options' => array('error_bubbling' => true),
        ));

        $this->assertTrue($form->getConfig()->getOption('error_bubbling'));
        $this->assertFalse($form['first']->getConfig()->getOption('error_bubbling'));
        $this->assertTrue($form['second']->getConfig()->getOption('error_bubbling'));
    }

    public function testSetOptionsPerChildAndOverwrite()
    {
        $form = $this->factory->create('repeated', null, array(
            'type'           => 'text',
            'options'        => array('label' => 'Label'),
            'second_options' => array('label' => 'Second label')
        ));

        $this->assertEquals('Label', $form['first']->getConfig()->getOption('label'));
        $this->assertEquals('Second label', $form['second']->getConfig()->getOption('label'));
        $this->assertTrue($form['first']->isRequired());
        $this->assertTrue($form['second']->isRequired());
    }

    public function testSubmitUnequal()
    {
        $input = array('first' => 'foo', 'second' => 'bar');

        $this->form->submit($input);

        $this->assertEquals('foo', $this->form['first']->getViewData());
        $this->assertEquals('bar', $this->form['second']->getViewData());
        $this->assertFalse($this->form->isSynchronized());
        $this->assertEquals($input, $this->form->getViewData());
        $this->assertNull($this->form->getData());
    }

    public function testSubmitEqual()
    {
        $input = array('first' => 'foo', 'second' => 'foo');

        $this->form->submit($input);

        $this->assertEquals('foo', $this->form['first']->getViewData());
        $this->assertEquals('foo', $this->form['second']->getViewData());
        $this->assertTrue($this->form->isSynchronized());
        $this->assertEquals($input, $this->form->getViewData());
        $this->assertEquals('foo', $this->form->getData());
    }
}
