<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Test;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

abstract class TypeTestCase extends FormIntegrationTestCase
{
    /**
     * @var FormBuilder
     */
    protected $builder;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    public const PLACEHOLDER_OPTION_TEXT = 'My placeholder...';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->builder = new FormBuilder('', null, $this->dispatcher, $this->factory);
    }

    protected function tearDown(): void
    {
        if (\in_array(ValidatorExtensionTrait::class, class_uses($this))) {
            $this->validator = null;
        }
    }

    protected function getExtensions()
    {
        $extensions = [];

        if (\in_array(ValidatorExtensionTrait::class, class_uses($this))) {
            $extensions[] = $this->getValidatorExtension();
        }

        return $extensions;
    }

    public static function assertDateTimeEquals(\DateTime $expected, \DateTime $actual)
    {
        self::assertEquals($expected->format('c'), $actual->format('c'));
    }

    public static function assertDateIntervalEquals(\DateInterval $expected, \DateInterval $actual)
    {
        self::assertEquals($expected->format('%RP%yY%mM%dDT%hH%iM%sS'), $actual->format('%RP%yY%mM%dDT%hH%iM%sS'));
    }

    public function testTextTypePlaceholderOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
        $this->assertSame(static::PLACEHOLDER_OPTION_TEXT, $form->createView()->vars['attr']['placeholder']);
    }

    public function testEmailTypePlaceholderOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\EmailType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
        $this->assertSame(static::PLACEHOLDER_OPTION_TEXT, $form->createView()->vars['attr']['placeholder']);
    }

    public function testTextareaTypePlaceholderOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\TextareaType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
        $this->assertSame(static::PLACEHOLDER_OPTION_TEXT, $form->createView()->vars['attr']['placeholder']);
    }

    public function testSearchTypePlaceholderOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\SearchType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
        $this->assertSame(static::PLACEHOLDER_OPTION_TEXT, $form->createView()->vars['attr']['placeholder']);
    }

    public function testUrlTypePlaceholderOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\UrlType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
        $this->assertSame(static::PLACEHOLDER_OPTION_TEXT, $form->createView()->vars['attr']['placeholder']);
    }

    public function testRangeTypePlaceholderOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\RangeType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
        $this->assertSame(static::PLACEHOLDER_OPTION_TEXT, $form->createView()->vars['attr']['placeholder']);
    }

    public function testTelTypePlaceholderOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\TelType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
        $this->assertSame(static::PLACEHOLDER_OPTION_TEXT, $form->createView()->vars['attr']['placeholder']);
    }

    public function testColorTypePlaceholderOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ColorType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
        $this->assertSame(static::PLACEHOLDER_OPTION_TEXT, $form->createView()->vars['attr']['placeholder']);
    }

    public function testPasswordTypePlaceholderOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\PasswordType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
        $this->assertSame(static::PLACEHOLDER_OPTION_TEXT, $form->createView()->vars['attr']['placeholder']);
    }

    public function testIntegerTypePlaceholderOption()
    {
        $this->expectException(UndefinedOptionsException::class);
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\IntegerType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
    }

    /**
     * @expectedException UndefinedOptionsException
     */
    public function testMoneyTypePlaceholderOption()
    {
        $this->expectException(UndefinedOptionsException::class);
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\MoneyType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
    }

    /**
     * @expectedException UndefinedOptionsException
     */
    public function testNumberTypePlaceholderOption()
    {
        $this->expectException(UndefinedOptionsException::class);
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\NumberType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
    }

    /**
     * @expectedException UndefinedOptionsException
     */
    public function testPercentTypePlaceholderOption()
    {
        $this->expectException(UndefinedOptionsException::class);
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\PercentType', null, [
            'placeholder' => static::PLACEHOLDER_OPTION_TEXT
        ]);
    }
}
