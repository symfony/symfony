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

use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * @author Stepan Anchugov <kixxx1@gmail.com>
 */
class BirthdayTypeTest extends DateTypeTest
{
    public const TESTED_TYPE = BirthdayType::class;

    public function testSetInvalidYearsOption()
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->create(static::TESTED_TYPE, null, [
            'years' => 'bad value',
            'widget' => 'choice',
        ]);
    }

    public function testWidgetSingleTextHasDefaultAttrMinMax()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ]);
        $formView = $form->createView();
        $options = $form->getConfig()->getOptions();
        $expectedMin = \sprintf('%d-01-01', min($options['years']));
        $expectedMax = \sprintf('%d-12-31', max($options['years']));
        $this->assertSame($expectedMin, $formView->vars['attr']['min']);
        $this->assertSame($expectedMax, $formView->vars['attr']['max']);
    }

    public function testWidgetSingleTextDoesntRemoveUserAttr()
    {
        $expectedMin = date('Y-m-d', strtotime('10 years ago'));
        $expectedMax = date('Y-m-d', strtotime('1 years ago'));
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
            'attr' => [
                'min' => $expectedMin,
                'max' => $expectedMax,
            ],
        ]);
        $formView = $form->createView();
        $this->assertSame($expectedMin, $formView->vars['attr']['min']);
        $this->assertSame($expectedMax, $formView->vars['attr']['max']);
    }

    public function testWidgetChoiceDoesNotSetMinMaxAttr()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
        ]);
        $formView = $form->createView();
        $this->assertArrayNotHasKey('min', $formView->vars['attr']);
        $this->assertArrayNotHasKey('max', $formView->vars['attr']);
    }
}
