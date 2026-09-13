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

use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Type\BoundsType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Tests\Fixtures\Suit;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class BoundsTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = BoundsType::class;

    public function testSubmitBothBounds()
    {
        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit(['from' => 'a', 'to' => 'z']);

        $this->assertSame(['from' => 'a', 'to' => 'z'], $form->getData());
        $this->assertSame('a', $form['from']->getData());
        $this->assertSame('z', $form['to']->getData());
    }

    public function testInnerTypeIsUsedForBothBounds()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
        ]);
        $form->submit(['from' => '18', 'to' => '120']);

        $this->assertSame(['from' => 18, 'to' => 120], $form->getData());
    }

    public function testChoiceInnerTypeRendersTwoSelects()
    {
        $ages = range(18, 25);

        $view = $this->factory->create(static::TESTED_TYPE, ['from' => 20, 'to' => 24], [
            'type' => ChoiceType::class,
            'options' => ['choices' => array_combine($ages, $ages)],
        ])->createView();

        $this->assertCount(8, $view['from']->vars['choices']);
        $this->assertCount(8, $view['to']->vars['choices']);
        $this->assertSame('20', $view['from']->vars['value']);
        $this->assertSame('24', $view['to']->vars['value']);
    }

    public function testOptionsArePassedToBothBounds()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => MoneyType::class,
            'options' => ['currency' => 'EUR'],
        ])->createView();

        // the exact pattern depends on the default locale, both bounds share it
        $this->assertStringContainsString('€', $view['from']->vars['money_pattern']);
        $this->assertSame($view['from']->vars['money_pattern'], $view['to']->vars['money_pattern']);
    }

    public function testBoundOptionsOverrideSharedOptions()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'options' => ['label' => 'shared'],
            'from_options' => ['label' => 'label.from'],
            'to_options' => ['label' => 'label.to'],
        ])->createView();

        $this->assertSame('label.from', $view['from']->vars['label']);
        $this->assertSame('label.to', $view['to']->vars['label']);
    }

    public function testBoundOptionsAreIndependent()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'from_options' => ['label' => 'label.from'],
        ])->createView();

        $this->assertSame('label.from', $view['from']->vars['label']);
        $this->assertNull($view['to']->vars['label']);
    }

    public function testRequiredIsPassedToBounds()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, ['required' => false])->createView();

        $this->assertFalse($view['from']->vars['required']);
        $this->assertFalse($view['to']->vars['required']);
    }

    public function testTranslationDomainIsPassedToBounds()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'translation_domain' => 'forms',
        ])->createView();

        $this->assertSame('forms', $view['from']->vars['translation_domain']);
        $this->assertSame('forms', $view['to']->vars['translation_domain']);
    }

    public function testABoundReportsItsOwnErrors()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'invalid_message' => 'Please enter a valid age range.',
        ]);
        $form->submit(['from' => 'not a number', 'to' => '120']);

        $this->assertFalse($form->isValid());
        $this->assertCount(0, $form->getErrors());
        $this->assertCount(0, $form['to']->getErrors());
        $this->assertCount(1, $errors = $form['from']->getErrors());
        $this->assertSame('Please enter a valid age range.', $errors[0]->getMessageTemplate());
    }

    public function testErrorBubblingCanBeTurnedOnPerBound()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'options' => ['error_bubbling' => true],
        ]);
        $form->submit(['from' => 'not a number', 'to' => '120']);

        $this->assertCount(0, $form['from']->getErrors());
        $this->assertCount(1, $form->getErrors());
    }

    public function testACompoundInnerTypeDoesNotBubbleItsErrorsToTheRange()
    {
        // FormType bubbles the errors of a compound form by default, which would move them
        // to the range, where "bounds_row" does not render them
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => FormType::class,
        ]);

        $this->assertFalse($form['from']->getConfig()->getErrorBubbling());
        $this->assertFalse($form['to']->getConfig()->getErrorBubbling());
    }

    public function testCompoundInnerType()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => DateType::class,
            'options' => ['widget' => 'choice', 'input' => 'string', 'years' => range(1970, 2000)],
        ]);
        $form->submit([
            'from' => ['year' => '1980', 'month' => '1', 'day' => '15'],
            'to' => ['year' => '1995', 'month' => '12', 'day' => '31'],
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame(['from' => '1980-01-15', 'to' => '1995-12-31'], $form->getData());
    }

    public function testCompoundInnerTypeKeepsItsOwnEmptyData()
    {
        // the bounds keep the empty data of the inner type, which is [] when that type is compound
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => DateType::class,
            'options' => ['widget' => 'choice', 'input' => 'string', 'years' => range(1970, 2000)],
        ]);

        $this->assertSame([], $form['from']->getConfig()->getEmptyData());
        $this->assertSame([], $form['to']->getConfig()->getEmptyData());

        $form->submit(null);

        $this->assertTrue($form->isSynchronized());
        $this->assertNull($form->getData());
    }

    public function testEmptyDataCascadesToBounds()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'empty_data' => ['from' => '18'],
        ]);

        $this->assertSame('18', $form['from']->getConfig()->getEmptyData());

        $form->submit(['from' => '', 'to' => '']);

        // the bound the parent says nothing about falls back to the inner type
        $this->assertSame(['from' => 18, 'to' => null], $form->getData());
    }

    public function testInvalidTypeOption()
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(static::TESTED_TYPE, null, ['type' => 123]);
    }

    public function testInvalidBoundOptions()
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(static::TESTED_TYPE, null, ['from_options' => 'label.from']);
    }

    public function testTheTypeCannotBeMadeSimple()
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(static::TESTED_TYPE, null, ['compound' => false]);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        // without a view transformer the empty range does not rewrite the view data
        parent::testSubmitNull(null, null, null);
    }

    public function testMapsToAnObject()
    {
        $form = $this->factory->create(static::TESTED_TYPE, new BoundsTypeRange(1, 9), [
            'type' => IntegerType::class,
            'data_class' => BoundsTypeRange::class,
        ]);
        $form->submit(['from' => '2', 'to' => '8']);

        $range = $form->getData();

        $this->assertInstanceOf(BoundsTypeRange::class, $range);
        $this->assertSame(2, $range->from);
        $this->assertSame(8, $range->to);
    }

    public function testMapsToAnObjectWhenTheInitialDataIsNull()
    {
        // the empty data the range is mapped onto is the one FormType derives from
        // "data_class", not an array
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'data_class' => BoundsTypeRange::class,
        ]);
        $form->submit(['from' => '2', 'to' => '8']);

        $range = $form->getData();

        $this->assertInstanceOf(BoundsTypeRange::class, $range);
        $this->assertSame(2, $range->from);
        $this->assertSame(8, $range->to);
    }

    public function testBoundsReachOtherPropertiesThroughPropertyPath()
    {
        $form = $this->factory->create(static::TESTED_TYPE, new BoundsTypeLimits(1, 9), [
            'type' => IntegerType::class,
            'data_class' => BoundsTypeLimits::class,
            'from_options' => ['property_path' => 'min'],
            'to_options' => ['property_path' => 'max'],
        ]);

        $this->assertSame('1', $form['from']->getViewData());
        $this->assertSame('9', $form['to']->getViewData());

        $form->submit(['from' => '2', 'to' => '8']);

        $limits = $form->getData();

        $this->assertSame(2, $limits->min);
        $this->assertSame(8, $limits->max);
    }

    public function testKeysTheTypeKnowsNothingAboutSurviveARoundTrip()
    {
        $form = $this->factory->create(static::TESTED_TYPE, ['from' => 1, 'to' => 9, 'label' => 'shipping'], [
            'type' => IntegerType::class,
        ]);
        $form->submit(['from' => '2', 'to' => '8']);

        $this->assertSame(['from' => 2, 'to' => 8, 'label' => 'shipping'], $form->getData());
    }

    public function testSubmitEmptyBoundsRevertsToNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['type' => IntegerType::class]);
        $form->submit(['from' => '', 'to' => '']);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitHalfOpenRangeIsKept()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['type' => IntegerType::class]);
        $form->submit(['from' => '18', 'to' => '']);

        $this->assertSame(['from' => 18, 'to' => null], $form->getData());
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = [], $expectedData = [])
    {
        $emptyData = ['from' => '18', 'to' => '120'];

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        $this->assertSame(['from' => 18, 'to' => 120], $form->getData());
    }

    public function testOrderingIsNotCheckedByDefault()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['type' => IntegerType::class]);
        $form->submit(['from' => '120', 'to' => '18']);

        $this->assertCount(0, $form->getErrors(true));
    }

    public function testCompareReportsOutOfOrderBoundsOnTheLowerBound()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'compare' => true,
        ]);
        $form->submit(['from' => '120', 'to' => '18']);

        $this->assertFalse($form->isValid());
        $this->assertCount(0, $form->getErrors());
        $this->assertCount(0, $form['to']->getErrors());
        $this->assertCount(1, $errors = $form['from']->getErrors());
        $this->assertSame('The lower bound must not be greater than the upper bound.', $errors[0]->getMessageTemplate());
    }

    public function testCompareLeavesAnOrderedRangeAlone()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'compare' => true,
        ]);
        $form->submit(['from' => '18', 'to' => '120']);

        $this->assertCount(0, $form->getErrors(true));
    }

    public function testCompareAcceptsEqualBounds()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'compare' => true,
        ]);
        $form->submit(['from' => '18', 'to' => '18']);

        $this->assertCount(0, $form->getErrors(true));
    }

    public function testCompareLeavesAHalfOpenRangeAlone()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'compare' => true,
        ]);
        $form->submit(['from' => '120', 'to' => '']);

        $this->assertCount(0, $form->getErrors(true));
        $this->assertSame(['from' => 120, 'to' => null], $form->getData());
    }

    public function testCompareLeavesABoundThatFailedToTransformAlone()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'compare' => true,
        ]);
        $form->submit(['from' => 'not a number', 'to' => '18']);

        // the bound already reports the transformation failure, nothing is added on top of it
        $this->assertCount(1, $errors = $form['from']->getErrors());
        $this->assertSame('Please enter a valid range.', $errors[0]->getMessageTemplate());
    }

    public function testCompareOrdersDates()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => DateType::class,
            'options' => ['widget' => 'choice', 'years' => range(1970, 2000)],
            'compare' => true,
        ]);
        $form->submit([
            'from' => ['year' => '1995', 'month' => '12', 'day' => '31'],
            'to' => ['year' => '1980', 'month' => '1', 'day' => '15'],
        ]);

        $this->assertCount(1, $form['from']->getErrors());
    }

    public function testCompareOrdersEnumsByTheirDeclarationOrder()
    {
        // Suit is declared Hearts, Diamonds, Clubs, Spades, an order its backing values do not follow
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => EnumType::class,
            'options' => ['class' => Suit::class],
            'compare' => true,
        ]);
        $form->submit(['from' => 'C', 'to' => 'H']);

        $this->assertSame(['from' => Suit::Clubs, 'to' => Suit::Hearts], $form->getData());
        $this->assertCount(1, $form['from']->getErrors());
    }

    public function testCompareLeavesEnumsInDeclarationOrderAlone()
    {
        // ordered by declaration, although "H" is greater than "C" as a string
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => EnumType::class,
            'options' => ['class' => Suit::class],
            'compare' => true,
        ]);
        $form->submit(['from' => 'H', 'to' => 'C']);

        $this->assertCount(0, $form->getErrors(true));
    }

    public function testCompareOrdersEnumsWhateverTheInnerTypeIs()
    {
        // the ordering follows the submitted data, not the inner type: a ChoiceType
        // listing enum cases is ordered the same way EnumType is
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => ChoiceType::class,
            'options' => [
                'choices' => Suit::cases(),
                'choice_value' => static fn (?Suit $suit) => $suit?->value,
                'choice_label' => static fn (Suit $suit) => $suit->name,
            ],
            'compare' => true,
        ]);
        $form->submit(['from' => 'C', 'to' => 'H']);

        $this->assertSame(['from' => Suit::Clubs, 'to' => Suit::Hearts], $form->getData());
        $this->assertCount(1, $form['from']->getErrors());
    }

    public function testCompareMessageCanBeCustomized()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => IntegerType::class,
            'compare' => true,
            'compare_message' => 'The minimum age must not be greater than the maximum one.',
        ]);
        $form->submit(['from' => '120', 'to' => '18']);

        $this->assertCount(1, $errors = $form['from']->getErrors());
        $this->assertSame('The minimum age must not be greater than the maximum one.', $errors[0]->getMessageTemplate());
    }

    public function testCompareWithACallable()
    {
        // an inner type the component cannot order on its own: the shortest string comes first
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'compare' => static fn (string $from, string $to): int => \strlen($from) <=> \strlen($to),
        ]);
        $form->submit(['from' => 'zzz', 'to' => 'a']);

        $this->assertCount(1, $form['from']->getErrors());
    }

    public function testCompareOrdersDatesWhateverTheInputOptionIs()
    {
        // the bounds reach the comparison as dates, not as the "d/m/Y" strings the
        // model holds, which would sort 31/12/1980 after 15/01/1995
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => DateType::class,
            'options' => [
                'widget' => 'choice',
                'years' => range(1970, 2000),
                'input' => 'string',
                'input_format' => 'd/m/Y',
            ],
            'compare' => true,
        ]);
        $form->submit([
            'from' => ['year' => '1980', 'month' => '12', 'day' => '31'],
            'to' => ['year' => '1995', 'month' => '1', 'day' => '15'],
        ]);

        $this->assertSame(['from' => '31/12/1980', 'to' => '15/01/1995'], $form->getData());
        $this->assertCount(0, $form->getErrors(true));
    }

    public function testCompareThrowsForBoundsItCannotOrderOnItsOwn()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'type' => ChoiceType::class,
            'options' => ['choices' => ['a' => 'a', 'b' => 'b'], 'multiple' => true],
            'compare' => true,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "compare" option cannot order bounds of type "array", pass a callable comparing them instead.');

        $form->submit(['from' => ['a', 'b'], 'to' => ['a']]);
    }

    public function testInvalidCompareOption()
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(static::TESTED_TYPE, null, ['compare' => 'yes']);
    }
}

class BoundsTypeRange
{
    public function __construct(
        public ?int $from = null,
        public ?int $to = null,
    ) {
    }
}

class BoundsTypeLimits
{
    public function __construct(
        public ?int $min = null,
        public ?int $max = null,
    ) {
    }
}
