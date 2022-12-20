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

use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class CheckboxTypeTest extends BaseTypeTest
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\CheckboxType';

    public function testDataIsFalseByDefault()
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        self::assertFalse($form->getData());
        self::assertFalse($form->getNormData());
        self::assertNull($form->getViewData());
    }

    public function testPassValueToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, ['value' => 'foobar'])
            ->createView();

        self::assertEquals('foobar', $view->vars['value']);
    }

    public function testCheckedIfDataTrue()
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->setData(true)
            ->createView();

        self::assertTrue($view->vars['checked']);
    }

    public function testCheckedIfDataTrueWithEmptyValue()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, ['value' => ''])
            ->setData(true)
            ->createView();

        self::assertTrue($view->vars['checked']);
    }

    public function testNotCheckedIfDataFalse()
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->setData(false)
            ->createView();

        self::assertFalse($view->vars['checked']);
    }

    public function testSubmitWithValueChecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => 'foobar',
        ]);
        $form->submit('foobar');

        self::assertTrue($form->getData());
        self::assertEquals('foobar', $form->getViewData());
    }

    public function testSubmitWithRandomValueChecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => 'foobar',
        ]);
        $form->submit('krixikraxi');

        self::assertTrue($form->getData());
        self::assertEquals('foobar', $form->getViewData());
    }

    public function testSubmitWithValueUnchecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => 'foobar',
        ]);
        $form->submit(null);

        self::assertFalse($form->getData());
        self::assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueChecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => '',
        ]);
        $form->submit('');

        self::assertTrue($form->getData());
        self::assertSame('', $form->getViewData());
    }

    public function testSubmitWithEmptyValueUnchecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => '',
        ]);
        $form->submit(null);

        self::assertFalse($form->getData());
        self::assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueAndFalseUnchecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => '',
        ]);
        $form->submit(false);

        self::assertFalse($form->getData());
        self::assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueAndTrueChecked()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'value' => '',
        ]);
        $form->submit(true);

        self::assertTrue($form->getData());
        self::assertSame('', $form->getViewData());
    }

    /**
     * @dataProvider provideCustomModelTransformerData
     */
    public function testCustomModelTransformer($data, $checked)
    {
        // present a binary status field as a checkbox
        $transformer = new CallbackTransformer(
            function ($value) {
                return 'checked' == $value;
            },
            function ($value) {
                return $value ? 'checked' : 'unchecked';
            }
        );

        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addModelTransformer($transformer)
            ->getForm();

        $form->setData($data);
        $view = $form->createView();

        self::assertSame($data, $form->getData());
        self::assertSame($checked, $form->getNormData());
        self::assertEquals($checked, $view->vars['checked']);
    }

    public function provideCustomModelTransformerData()
    {
        return [
            ['checked', true],
            ['unchecked', false],
        ];
    }

    /**
     * @dataProvider provideCustomFalseValues
     */
    public function testCustomFalseValues($falseValue)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'false_values' => [$falseValue],
        ]);
        $form->submit($falseValue);
        self::assertFalse($form->getData());
    }

    public function provideCustomFalseValues()
    {
        return [
            [''],
            ['false'],
            ['0'],
        ];
    }

    public function testDontAllowNonArrayFalseValues()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessageMatches('/"false_values" with value "invalid" is expected to be of type "array"/');
        $this->factory->create(static::TESTED_TYPE, null, [
            'false_values' => 'invalid',
        ]);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull(false, false, null);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'empty', $expectedData = true)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        // view data is transformed to the string true value
        self::assertSame('1', $form->getViewData());
        self::assertSame($expectedData, $form->getNormData());
        self::assertSame($expectedData, $form->getData());
    }

    public function testSubmitNullIsEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        $form->submit(null);

        self::assertTrue($form->isEmpty());
    }
}
