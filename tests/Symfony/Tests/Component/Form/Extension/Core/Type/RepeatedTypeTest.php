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

use Symfony\Component\Form\RepeatedField;
use Symfony\Component\Form\Field;

class RepeatedTypeTest extends TypeTestCase
{
    protected $form;
    protected $optionsForm;

    protected function setUp()
    {
        parent::setUp();

        $this->form = $this->factory->create('repeated', null, array(
            'type' => 'field',
        ));
        $this->form->setData(null);

        $this->optionsForm = $this->factory->create('repeated', null, array(
            'type'           => 'field',
            'options'        => array('label'    => 'Test'),
            'first_options'  => array('required' => false),
            'second_options' => array('label'    => 'Test2'),
        ));
    }

    public function testSetData()
    {
        $this->form->setData('foobar');

        $this->assertEquals('foobar', $this->form['first']->getData());
        $this->assertEquals('foobar', $this->form['second']->getData());
    }

    public function testSetOptions()
    {
        $first  = $this->optionsForm['first'];
        $second = $this->optionsForm['second'];

        $this->assertEquals('Test', $first->getAttribute('label'));
        $this->assertEquals('Test2', $second->getAttribute('label'));
        $this->assertFalse($first->isRequired());
        $this->assertTrue($second->isRequired());
    }

    public function testSubmitUnequal()
    {
        $input = array('first' => 'foo', 'second' => 'bar');

        $this->form->bind($input);

        $this->assertEquals('foo', $this->form['first']->getClientData());
        $this->assertEquals('bar', $this->form['second']->getClientData());
        $this->assertFalse($this->form->isSynchronized());
        $this->assertEquals($input, $this->form->getClientData());
        $this->assertEquals(null, $this->form->getData());
    }

    public function testSubmitEqual()
    {
        $input = array('first' => 'foo', 'second' => 'foo');

        $this->form->bind($input);

        $this->assertEquals('foo', $this->form['first']->getClientData());
        $this->assertEquals('foo', $this->form['second']->getClientData());
        $this->assertTrue($this->form->isSynchronized());
        $this->assertEquals($input, $this->form->getClientData());
        $this->assertEquals('foo', $this->form->getData());
    }
}
