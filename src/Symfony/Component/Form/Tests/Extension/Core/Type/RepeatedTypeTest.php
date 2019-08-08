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

use Symfony\Component\Form\Form;

class RepeatedTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\RepeatedType';

    /**
     * @var Form
     */
    protected $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
        ]);
    }

    public function testSetData()
    {
        $this->form->setData('foobar');

        $this->assertSame('foobar', $this->form['first']->getData());
        $this->assertSame('foobar', $this->form['second']->getData());
    }

    public function testSetOptions()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
            'options' => ['label' => 'Global'],
        ]);

        $this->assertSame('Global', $form['first']->getConfig()->getOption('label'));
        $this->assertSame('Global', $form['second']->getConfig()->getOption('label'));
        $this->assertTrue($form['first']->isRequired());
        $this->assertTrue($form['second']->isRequired());
    }

    public function testSetOptionsPerChild()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            // the global required value cannot be overridden
            'type' => TextTypeTest::TESTED_TYPE,
            'first_options' => ['label' => 'Test', 'required' => false],
            'second_options' => ['label' => 'Test2'],
        ]);

        $this->assertSame('Test', $form['first']->getConfig()->getOption('label'));
        $this->assertSame('Test2', $form['second']->getConfig()->getOption('label'));
        $this->assertTrue($form['first']->isRequired());
        $this->assertTrue($form['second']->isRequired());
    }

    public function testSetRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'type' => TextTypeTest::TESTED_TYPE,
        ]);

        $this->assertFalse($form['first']->isRequired());
        $this->assertFalse($form['second']->isRequired());
    }

    public function testSetInvalidOptions()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException');
        $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
            'options' => 'bad value',
        ]);
    }

    public function testSetInvalidFirstOptions()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException');
        $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
            'first_options' => 'bad value',
        ]);
    }

    public function testSetInvalidSecondOptions()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException');
        $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
            'second_options' => 'bad value',
        ]);
    }

    public function testSetErrorBubblingToTrue()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'error_bubbling' => true,
        ]);

        $this->assertTrue($form->getConfig()->getOption('error_bubbling'));
        $this->assertTrue($form['first']->getConfig()->getOption('error_bubbling'));
        $this->assertTrue($form['second']->getConfig()->getOption('error_bubbling'));
    }

    public function testSetErrorBubblingToFalse()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'error_bubbling' => false,
        ]);

        $this->assertFalse($form->getConfig()->getOption('error_bubbling'));
        $this->assertFalse($form['first']->getConfig()->getOption('error_bubbling'));
        $this->assertFalse($form['second']->getConfig()->getOption('error_bubbling'));
    }

    public function testSetErrorBubblingIndividually()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'error_bubbling' => true,
            'options' => ['error_bubbling' => false],
            'second_options' => ['error_bubbling' => true],
        ]);

        $this->assertTrue($form->getConfig()->getOption('error_bubbling'));
        $this->assertFalse($form['first']->getConfig()->getOption('error_bubbling'));
        $this->assertTrue($form['second']->getConfig()->getOption('error_bubbling'));
    }

    public function testSetOptionsPerChildAndOverwrite()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
            'options' => ['label' => 'Label'],
            'second_options' => ['label' => 'Second label'],
        ]);

        $this->assertSame('Label', $form['first']->getConfig()->getOption('label'));
        $this->assertSame('Second label', $form['second']->getConfig()->getOption('label'));
        $this->assertTrue($form['first']->isRequired());
        $this->assertTrue($form['second']->isRequired());
    }

    public function testSubmitUnequal()
    {
        $input = ['first' => 'foo', 'second' => 'bar'];

        $this->form->submit($input);

        $this->assertSame('foo', $this->form['first']->getViewData());
        $this->assertSame('bar', $this->form['second']->getViewData());
        $this->assertFalse($this->form->isSynchronized());
        $this->assertSame($input, $this->form->getViewData());
        $this->assertNull($this->form->getData());
    }

    public function testSubmitEqual()
    {
        $input = ['first' => 'foo', 'second' => 'foo'];

        $this->form->submit($input);

        $this->assertSame('foo', $this->form['first']->getViewData());
        $this->assertSame('foo', $this->form['second']->getViewData());
        $this->assertTrue($this->form->isSynchronized());
        $this->assertSame($input, $this->form->getViewData());
        $this->assertSame('foo', $this->form->getData());
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, ['first' => null, 'second' => null]);
    }
}
