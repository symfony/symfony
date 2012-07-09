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

class RepeatedTypeTest extends TypeTestCase
{
    protected $form;

    protected function setUp()
    {
        parent::setUp();

        $this->form = $this->factory->create('repeated', null, array(
            'type' => 'field',
        ));
        $this->form->setData(null);
    }

    public function testSetData()
    {
        $this->form->setData('foobar');

        $this->assertEquals('foobar', $this->form['first']->getData());
        $this->assertEquals('foobar', $this->form['second']->getData());
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
