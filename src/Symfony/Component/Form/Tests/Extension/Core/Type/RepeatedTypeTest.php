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
use Symfony\Component\Form\Tests\Fixtures\NotMappedType;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class RepeatedTypeTest extends BaseTypeTest
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\RepeatedType';

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

        self::assertSame('foobar', $this->form['first']->getData());
        self::assertSame('foobar', $this->form['second']->getData());
    }

    public function testSetOptions()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
            'options' => ['label' => 'Global'],
        ]);

        self::assertSame('Global', $form['first']->getConfig()->getOption('label'));
        self::assertSame('Global', $form['second']->getConfig()->getOption('label'));
        self::assertTrue($form['first']->isRequired());
        self::assertTrue($form['second']->isRequired());
    }

    public function testSetOptionsPerChild()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            // the global required value cannot be overridden
            'type' => TextTypeTest::TESTED_TYPE,
            'first_options' => ['label' => 'Test', 'required' => false],
            'second_options' => ['label' => 'Test2'],
        ]);

        self::assertSame('Test', $form['first']->getConfig()->getOption('label'));
        self::assertSame('Test2', $form['second']->getConfig()->getOption('label'));
        self::assertTrue($form['first']->isRequired());
        self::assertTrue($form['second']->isRequired());
    }

    public function testSetRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'type' => TextTypeTest::TESTED_TYPE,
        ]);

        self::assertFalse($form['first']->isRequired());
        self::assertFalse($form['second']->isRequired());
    }

    public function testMappedOverridesDefault()
    {
        $form = $this->factory->create(NotMappedType::class);
        self::assertFalse($form->getConfig()->getMapped());

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => NotMappedType::class,
        ]);

        self::assertTrue($form['first']->getConfig()->getMapped());
        self::assertTrue($form['second']->getConfig()->getMapped());
    }

    /**
     * @dataProvider notMappedConfigurationKeys
     */
    public function testNotMappedInnerIsOverridden($configurationKey)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
            $configurationKey => ['mapped' => false],
        ]);

        self::assertTrue($form['first']->getConfig()->getMapped());
        self::assertTrue($form['second']->getConfig()->getMapped());
    }

    public function notMappedConfigurationKeys()
    {
        return [
            ['first_options'],
            ['second_options'],
        ];
    }

    public function testSetInvalidOptions()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
            'options' => 'bad value',
        ]);
    }

    public function testSetInvalidFirstOptions()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
            'first_options' => 'bad value',
        ]);
    }

    public function testSetInvalidSecondOptions()
    {
        self::expectException(InvalidOptionsException::class);
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

        self::assertTrue($form->getConfig()->getOption('error_bubbling'));
        self::assertTrue($form['first']->getConfig()->getOption('error_bubbling'));
        self::assertTrue($form['second']->getConfig()->getOption('error_bubbling'));
    }

    public function testSetErrorBubblingToFalse()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'error_bubbling' => false,
        ]);

        self::assertFalse($form->getConfig()->getOption('error_bubbling'));
        self::assertFalse($form['first']->getConfig()->getOption('error_bubbling'));
        self::assertFalse($form['second']->getConfig()->getOption('error_bubbling'));
    }

    public function testSetErrorBubblingIndividually()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'error_bubbling' => true,
            'options' => ['error_bubbling' => false],
            'second_options' => ['error_bubbling' => true],
        ]);

        self::assertTrue($form->getConfig()->getOption('error_bubbling'));
        self::assertFalse($form['first']->getConfig()->getOption('error_bubbling'));
        self::assertTrue($form['second']->getConfig()->getOption('error_bubbling'));
    }

    public function testSetOptionsPerChildAndOverwrite()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => TextTypeTest::TESTED_TYPE,
            'options' => ['label' => 'Label'],
            'second_options' => ['label' => 'Second label'],
        ]);

        self::assertSame('Label', $form['first']->getConfig()->getOption('label'));
        self::assertSame('Second label', $form['second']->getConfig()->getOption('label'));
        self::assertTrue($form['first']->isRequired());
        self::assertTrue($form['second']->isRequired());
    }

    public function testSubmitUnequal()
    {
        $input = ['first' => 'foo', 'second' => 'bar'];

        $this->form->submit($input);

        self::assertSame('foo', $this->form['first']->getViewData());
        self::assertSame('bar', $this->form['second']->getViewData());
        self::assertFalse($this->form->isSynchronized());
        self::assertSame($input, $this->form->getViewData());
        self::assertNull($this->form->getData());
    }

    public function testSubmitEqual()
    {
        $input = ['first' => 'foo', 'second' => 'foo'];

        $this->form->submit($input);

        self::assertSame('foo', $this->form['first']->getViewData());
        self::assertSame('foo', $this->form['second']->getViewData());
        self::assertTrue($this->form->isSynchronized());
        self::assertSame($input, $this->form->getViewData());
        self::assertSame('foo', $this->form->getData());
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, ['first' => null, 'second' => null]);
    }
}
