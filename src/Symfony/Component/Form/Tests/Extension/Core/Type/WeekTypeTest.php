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
use Symfony\Component\Form\Extension\Core\Type\WeekType;
use Symfony\Component\Form\FormError;

class WeekTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = WeekType::class;

    public function testSubmitArray(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
            'input' => 'array',
        ]);

        $form->submit([
            'year' => '2019',
            'week' => '1',
        ]);

        $this->assertSame(['year' => 2019, 'week' => 1], $form->getData());
    }

    public function testSubmitString(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'years' => [2019],
            'input' => 'string',
            'widget' => 'choice',
        ]);

        $form->submit([
            'year' => '2019',
            'week' => '1',
        ]);

        $this->assertEquals('2019-W01', $form->getData());
    }

    public function testSubmitStringSingleText(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'years' => [2019],
            'input' => 'string',
            'widget' => 'single_text',
        ]);

        $form->submit('2019-W01');

        $this->assertEquals('2019-W01', $form->getData());
    }

    public function testPassDefaultPlaceholderToViewIfNotRequired(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'widget' => 'choice',
        ])
            ->createView();

        $this->assertSame('', $view['year']->vars['placeholder']);
        $this->assertSame('', $view['week']->vars['placeholder']);
    }

    public function testPassNoPlaceholderToViewIfRequired(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => true,
            'widget' => 'choice',
        ])
            ->createView();

        $this->assertNull($view['year']->vars['placeholder']);
        $this->assertNull($view['week']->vars['placeholder']);
    }

    public function testPassPlaceholderAsString(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => 'Empty',
            'widget' => 'choice',
        ])
            ->createView();

        $this->assertSame('Empty', $view['year']->vars['placeholder']);
        $this->assertSame('Empty', $view['week']->vars['placeholder']);
    }

    public function testPassPlaceholderAsArray(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => [
                'year' => 'Empty year',
                'week' => 'Empty week',
            ],
            'widget' => 'choice',
        ])
            ->createView();

        $this->assertSame('Empty year', $view['year']->vars['placeholder']);
        $this->assertSame('Empty week', $view['week']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddEmptyIfNotRequired(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'placeholder' => [
                'year' => 'Empty year',
            ],
            'widget' => 'choice',
        ])
            ->createView();

        $this->assertSame('Empty year', $view['year']->vars['placeholder']);
        $this->assertSame('', $view['week']->vars['placeholder']);
    }

    public function testPassPlaceholderAsPartialArrayAddNullIfRequired(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => true,
            'placeholder' => [
                'year' => 'Empty year',
            ],
            'widget' => 'choice',
        ])
            ->createView();

        $this->assertSame('Empty year', $view['year']->vars['placeholder']);
        $this->assertNull($view['week']->vars['placeholder']);
    }

    public function testPassHtml5TypeIfSingleTextAndHtml5Format(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
        ])
            ->createView();

        $this->assertSame('week', $view->vars['type']);
    }

    public function testDontPassHtml5TypeIfHtml5NotAllowed(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
            'html5' => false,
        ])
            ->createView();

        $this->assertArrayNotHasKey('type', $view->vars);
    }

    public function testDontPassHtml5TypeIfNotSingleText(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'text',
        ])
            ->createView();

        $this->assertArrayNotHasKey('type', $view->vars);
    }

    public function testYearTypeChoiceErrorsBubbleUp(): void
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
        ]);

        $form['year']->addError($error);

        $this->assertSame([], iterator_to_array($form['year']->getErrors()));
        $this->assertSame([$error], iterator_to_array($form->getErrors()));
    }

    public function testWeekTypeChoiceErrorsBubbleUp(): void
    {
        $error = new FormError('Invalid!');
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
        ]);

        $form['week']->addError($error);

        $this->assertSame([], iterator_to_array($form['week']->getErrors()));
        $this->assertSame([$error], iterator_to_array($form->getErrors()));
    }

    public function testPassDefaultChoiceTranslationDomain(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
        ]);

        $view = $form->createView();

        $this->assertFalse($view['year']->vars['choice_translation_domain']);
        $this->assertFalse($view['week']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsString(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'choice_translation_domain' => 'messages',
            'widget' => 'choice',
        ]);

        $view = $form->createView();
        $this->assertSame('messages', $view['year']->vars['choice_translation_domain']);
        $this->assertSame('messages', $view['week']->vars['choice_translation_domain']);
    }

    public function testPassChoiceTranslationDomainAsArray(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'choice_translation_domain' => [
                'year' => 'foo',
                'week' => 'test',
            ],
            'widget' => 'choice',
        ]);

        $view = $form->createView();
        $this->assertSame('foo', $view['year']->vars['choice_translation_domain']);
        $this->assertSame('test', $view['week']->vars['choice_translation_domain']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null): void
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'widget' => 'choice',
        ]);
        $form->submit(null);

        $this->assertSame(['year' => null, 'week' => null], $form->getData());
        $this->assertSame(['year' => null, 'week' => null], $form->getNormData());
        $this->assertSame(['year' => null, 'week' => null], $form->getViewData());
    }

    public function testSubmitFromChoiceEmpty(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'choice',
            'required' => false,
        ]);

        $form->submit([
            'year' => '',
            'week' => '',
        ]);

        $this->assertSame(['year' => null, 'week' => null], $form->getData());
    }

    public function testSubmitNullWithText(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'text',
        ]);
        $form->submit(null);

        $this->assertSame(['year' => null, 'week' => null], $form->getViewData());
    }

    public function testSubmitNullWithSingleText(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => 'single_text',
            'input' => 'string',
        ]);
        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = [], $expectedData = null): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
            'widget' => 'choice',
        ]);
        $form->submit(null);

        $this->assertSame(['year' => null, 'week' => null], $form->getData());
    }

    #[DataProvider('provideEmptyData')]
    public function testSubmitNullUsesDateEmptyDataString($widget, $emptyData, $expectedData): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'widget' => $widget,
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        $this->assertSame($expectedData, $form->getData());
    }

    public static function provideEmptyData(): array
    {
        return [
            'Compound text field' => ['text', ['year' => '2019', 'week' => '1'], ['year' => 2019, 'week' => 1]],
            'Compound choice field' => ['choice', ['year' => '2019', 'week' => '1'], ['year' => 2019, 'week' => 1]],
        ];
    }
}
