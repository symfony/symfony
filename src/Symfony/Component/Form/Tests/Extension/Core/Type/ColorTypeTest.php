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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class ColorTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = ColorType::class;

    #[DataProvider('validationShouldPassProvider')]
    public function testValidationShouldPass(bool $html5, ?string $submittedValue)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'html5' => $html5,
            'trim' => true,
        ]);

        $form->submit($submittedValue);

        $this->assertInstanceOf(FormErrorIterator::class, $form->getErrors());
        $this->assertCount(0, $form->getErrors());
    }

    public static function validationShouldPassProvider(): array
    {
        return [
            [false, 'foo'],
            [false, null],
            [false, ''],
            [false, ' '],
            [true, '#000000'],
            [true, '#abcabc'],
            [true, '#BbBbBb'],
            [true, '#1Ee54d'],
            [true, ' #1Ee54d '],
            [true, null],
            [true, ''],
            [true, ' '],
        ];
    }

    #[DataProvider('validationShouldFailProvider')]
    public function testValidationShouldFail(string $expectedValueParameterValue, ?string $submittedValue, bool $trim = true)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'html5' => true,
            'trim' => $trim,
        ]);

        $form->submit($submittedValue);

        $expectedFormError = new FormError('This value is not a valid HTML5 color.', 'This value is not a valid HTML5 color.', [
            '{{ value }}' => $expectedValueParameterValue,
        ]);
        $expectedFormError->setOrigin($form);

        $this->assertEquals([$expectedFormError], iterator_to_array($form->getErrors()));
    }

    public static function validationShouldFailProvider(): array
    {
        return [
            ['foo', 'foo'],
            ['000000', '000000'],
            ['#abcabg', '#abcabg'],
            ['#12345', '#12345'],
            [' #ffffff ', ' #ffffff ', false],
        ];
    }

    public function testSubmitArrayWhenAllowed()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'html5' => true,
            'allow_array_submission' => true,
        ]);

        $form->submit(['#000000']);

        $this->assertFalse($form->isSynchronized());
        $this->assertSame('Submitted data was expected to be text or number, array given.', $form->getTransformationFailure()->getMessage());
        $errors = iterator_to_array($form->getErrors());
        $this->assertCount(1, $errors);
        $this->assertSame('Please select a valid color.', $errors[0]->getMessage());
    }

    public function testPreSubmitListenersCanTurnArraysIntoColors()
    {
        $form = $this->factory
            ->createBuilder(static::TESTED_TYPE, null, ['html5' => true, 'allow_array_submission' => true])
            ->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) {
                $event->setData($event->getData()['hex']);
            })
            ->getForm();

        $form->submit(['hex' => '#ff0000']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('#ff0000', $form->getData());
        $this->assertCount(0, $form->getErrors());
    }

    public function testArraySetByPreSubmitListenersIsReportedWhenNotAllowed()
    {
        $form = $this->factory
            ->createBuilder(static::TESTED_TYPE, null, ['html5' => true])
            ->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) {
                $event->setData(['#000000']);
            }, 1)
            ->getForm();

        $form->submit('#000000');

        $expectedFormError = new FormError('This value is not a valid HTML5 color.', 'This value is not a valid HTML5 color.', [
            '{{ value }}' => 'array',
        ]);
        $expectedFormError->setOrigin($form);

        $this->assertEquals([$expectedFormError], iterator_to_array($form->getErrors()));
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }
}
