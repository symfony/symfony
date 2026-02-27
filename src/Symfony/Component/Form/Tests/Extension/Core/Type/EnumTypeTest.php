<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Extension\Core\Type;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Tests\Extension\Core\Type\BaseTypeTestCase;
use Symfony\Component\Form\Tests\Fixtures\Answer;
use Symfony\Component\Form\Tests\Fixtures\Number;
use Symfony\Component\Form\Tests\Fixtures\Suit;
use Symfony\Component\Form\Tests\Fixtures\TranslatableTextAlign;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Contracts\Translation\TranslatableInterface;

class EnumTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = EnumType::class;

    public function testClassOptionIsRequired()
    {
        $this->expectException(MissingOptionsException::class);
        $this->factory->createNamed('name', $this->getTestedType());
    }

    public function testInvalidClassOption()
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->createNamed('name', $this->getTestedType(), null, [
            'class' => 'foo',
        ]);
    }

    public function testInvalidClassOptionType()
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->createNamed('name', $this->getTestedType(), null, [
            'class' => new \stdClass(),
        ]);
    }

    #[DataProvider('provideSingleSubmitData')]
    public function testSubmitSingleNonExpanded(string $class, string $submittedData, \UnitEnum $expectedData)
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => false,
            'class' => $class,
        ]);

        $form->submit($submittedData);

        $this->assertEquals($expectedData, $form->getData());
        $this->assertEquals($submittedData, $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    #[DataProvider('provideSingleSubmitData')]
    public function testSubmitSingleExpanded(string $class, string $submittedData, \UnitEnum $expectedData)
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => $class,
        ]);

        $form->submit($submittedData);

        $this->assertEquals($expectedData, $form->getData());
        $this->assertEquals($submittedData, $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public static function provideSingleSubmitData(): iterable
    {
        yield 'unbacked' => [
            Answer::class,
            '2',
            Answer::FourtyTwo,
        ];

        yield 'string backed' => [
            Suit::class,
            Suit::Spades->value,
            Suit::Spades,
        ];

        yield 'integer backed' => [
            Number::class,
            (string) Number::Two->value,
            Number::Two,
        ];
    }

    public function testSubmitSingleNonExpandedInvalidChoice()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => false,
            'class' => Suit::class,
        ]);

        $form->submit('foobar');

        $this->assertNull($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        $form = $this->factory->create($this->getTestedType(), null, $this->getTestOptions());

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'empty', $expectedData = null)
    {
        $emptyData = Suit::Hearts->value;

        $form = $this->factory->create($this->getTestedType(), null, [
            'class' => Suit::class,
            'empty_data' => $emptyData,
        ]);

        $form->submit(null);

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertSame(Suit::Hearts, $form->getNormData());
        $this->assertSame(Suit::Hearts, $form->getData());
    }

    public function testSubmitMultipleChoiceWithEmptyData()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => true,
            'expanded' => false,
            'class' => Suit::class,
            'empty_data' => [Suit::Diamonds->value],
        ]);

        $form->submit(null);

        $this->assertSame([Suit::Diamonds], $form->getData());
    }

    public function testSubmitSingleChoiceExpandedWithEmptyData()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => Suit::class,
            'empty_data' => Suit::Hearts->value,
        ]);

        $form->submit(null);

        $this->assertSame(Suit::Hearts, $form->getData());
    }

    public function testSubmitMultipleChoiceExpandedWithEmptyData()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => true,
            'expanded' => true,
            'class' => Suit::class,
            'empty_data' => [Suit::Spades->value],
        ]);

        $form->submit(null);

        $this->assertSame([Suit::Spades], $form->getData());
    }

    #[DataProvider('provideMultiSubmitData')]
    public function testSubmitMultipleNonExpanded(string $class, array $submittedValues, array $expectedValues)
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => true,
            'expanded' => false,
            'class' => $class,
        ]);

        $form->submit($submittedValues);

        $this->assertSame($expectedValues, $form->getData());
        $this->assertSame($submittedValues, $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    #[DataProvider('provideMultiSubmitData')]
    public function testSubmitMultipleExpanded(string $class, array $submittedValues, array $expectedValues)
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => true,
            'expanded' => true,
            'class' => $class,
        ]);

        $form->submit($submittedValues);

        $this->assertSame($expectedValues, $form->getData());
        $this->assertSame($submittedValues, $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public static function provideMultiSubmitData(): iterable
    {
        yield 'unbacked' => [
            Answer::class,
            ['0', '1'],
            [Answer::Yes, Answer::No],
        ];

        yield 'string backed' => [
            Suit::class,
            [Suit::Hearts->value, Suit::Spades->value],
            [Suit::Hearts, Suit::Spades],
        ];

        yield 'integer backed' => [
            Number::class,
            [(string) Number::Two->value, (string) Number::Three->value],
            [Number::Two, Number::Three],
        ];
    }

    public function testChoiceLabel()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => Answer::class,
        ]);

        $view = $form->createView();

        $this->assertSame('Yes', $view->children[0]->vars['label']);
    }

    public function testChoiceLabelTranslatable()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => TranslatableTextAlign::class,
        ]);

        $view = $form->createView();

        $this->assertInstanceOf(TranslatableInterface::class, $view->children[0]->vars['label']);
        $this->assertEquals('Left', $view->children[0]->vars['label']->trans(new IdentityTranslator()));
    }

    public function testChoices()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => Answer::class,
            'choices' => [
                Answer::Yes,
                Answer::No,
            ],
        ]);

        $view = $form->createView();

        $this->assertCount(2, $view->children);
        $this->assertSame('Yes', $view->children[0]->vars['label']);
        $this->assertSame('No', $view->children[1]->vars['label']);
    }

    public function testChoicesWithLabels()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => Answer::class,
            'choices' => [
                'yes' => Answer::Yes,
                'no' => Answer::No,
            ],
        ]);

        $view = $form->createView();

        $this->assertSame('yes', $view->children[0]->vars['label']);
        $this->assertSame('no', $view->children[1]->vars['label']);
    }

    public function testGroupedEnumChoices()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => Answer::class,
            'choices' => [
                'Group 1' => [Answer::Yes, Answer::No],
                'Group 2' => [Answer::FourtyTwo],
            ],
        ]);
        $view = $form->createView();
        $this->assertCount(2, $view->vars['choices']['Group 1']->choices);
        $this->assertSame('Yes', $view->vars['choices']['Group 1']->choices[0]->label);
        $this->assertSame('No', $view->vars['choices']['Group 1']->choices[1]->label);
        $this->assertCount(1, $view->vars['choices']['Group 2']->choices);
        $this->assertSame('FourtyTwo', $view->vars['choices']['Group 2']->choices[2]->label);
    }

    public function testGroupedEnumChoicesWithCustomLabels()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => Answer::class,
            'choices' => [
                'Group 1' => [
                    'Custom Yes' => Answer::Yes,
                    'Custom No' => Answer::No,
                ],
                'Group 2' => [
                    'Custom 42' => Answer::FourtyTwo,
                ],
            ],
        ]);
        $view = $form->createView();

        // Test Group 1
        $this->assertCount(2, $view->vars['choices']['Group 1']->choices);
        $this->assertSame('Custom Yes', $view->vars['choices']['Group 1']->choices[0]->label);
        $this->assertSame('Custom No', $view->vars['choices']['Group 1']->choices[1]->label);

        // Test Group 2
        $this->assertCount(1, $view->vars['choices']['Group 2']->choices);
        $this->assertSame('Custom 42', $view->vars['choices']['Group 2']->choices[2]->label);
    }

    public function testMixedGroupedAndSingleChoices()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => Answer::class,
            'choices' => [
                'Group 1' => [Answer::Yes, Answer::No],
                'Custom 42' => Answer::FourtyTwo,
            ],
        ]);
        $view = $form->createView();

        // Group 1 (simple list) → enum names
        $this->assertInstanceOf(ChoiceGroupView::class, $view->vars['choices']['Group 1']);
        $this->assertCount(2, $view->vars['choices']['Group 1']->choices);
        $this->assertSame('Yes', $view->vars['choices']['Group 1']->choices[0]->label);
        $this->assertSame('No', $view->vars['choices']['Group 1']->choices[1]->label);

        // Single custom → custom label (treated as flat choice)
        $customChoice = $view->vars['choices'][2];
        $this->assertInstanceOf(ChoiceView::class, $customChoice);
        $this->assertSame('Custom 42', $customChoice->label);
    }

    public function testMixedLabeledAndUnlabeledChoices()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => Answer::class,
            'choices' => [
                Answer::Yes,
                Answer::No,
                'Custom 42' => Answer::FourtyTwo,
            ],
        ]);
        $view = $form->createView();
        // Assertions: names for unlabeled, custom for labeled
        $children = array_values($view->children); // Numeric access
        $this->assertSame('Yes', $children[0]->vars['label']);
        $this->assertSame('No', $children[1]->vars['label']);
        $this->assertSame('Custom 42', $children[2]->vars['label']);
    }

    public function testEnumChoicesWithNumericCustomLabels()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => Answer::class,
            'choice_label' => null, // Explicitly override to use keys as labels for numeric customs
            'choices' => [
                '34' => Answer::Yes,
                '2' => Answer::No,
            ],
        ]);
        $view = $form->createView();
        $this->assertSame('34', $view->children[0]->vars['label']);
        $this->assertSame('2', $view->children[1]->vars['label']);
    }

    protected function getTestOptions(): array
    {
        return ['class' => Suit::class];
    }
}
