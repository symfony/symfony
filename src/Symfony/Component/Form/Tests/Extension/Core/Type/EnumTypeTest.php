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

use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Tests\Extension\Core\Type\BaseTypeTest;
use Symfony\Component\Form\Tests\Fixtures\Answer;
use Symfony\Component\Form\Tests\Fixtures\Number;
use Symfony\Component\Form\Tests\Fixtures\Suit;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

/**
 * @requires PHP 8.1
 */
final class EnumTypeTest extends BaseTypeTest
{
    public const TESTED_TYPE = EnumType::class;

    public function testClassOptionIsRequired()
    {
        self::expectException(MissingOptionsException::class);
        $this->factory->createNamed('name', $this->getTestedType());
    }

    public function testInvalidClassOption()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->createNamed('name', $this->getTestedType(), null, [
            'class' => 'foo',
        ]);
    }

    public function testInvalidClassOptionType()
    {
        self::expectException(InvalidOptionsException::class);
        $this->factory->createNamed('name', $this->getTestedType(), null, [
            'class' => new \stdClass(),
        ]);
    }

    /**
     * @dataProvider provideSingleSubmitData
     */
    public function testSubmitSingleNonExpanded(string $class, string $submittedData, \UnitEnum $expectedData)
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => false,
            'class' => $class,
        ]);

        $form->submit($submittedData);

        self::assertEquals($expectedData, $form->getData());
        self::assertEquals($submittedData, $form->getViewData());
        self::assertTrue($form->isSynchronized());
    }

    /**
     * @dataProvider provideSingleSubmitData
     */
    public function testSubmitSingleExpanded(string $class, string $submittedData, \UnitEnum $expectedData)
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => $class,
        ]);

        $form->submit($submittedData);

        self::assertEquals($expectedData, $form->getData());
        self::assertEquals($submittedData, $form->getViewData());
        self::assertTrue($form->isSynchronized());
    }

    public function provideSingleSubmitData(): iterable
    {
        yield 'unbacked' => [
            Answer::class,
            '2',
            Answer::FourtyTwo,
        ];

        yield 'string backed' => [
            Suit::class,
            (Suit::Spades)->value,
            Suit::Spades,
        ];

        yield 'integer backed' => [
            Number::class,
            (string) (Number::Two)->value,
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

        self::assertNull($form->getData());
        self::assertEquals('foobar', $form->getViewData());
        self::assertFalse($form->isSynchronized());
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        $form = $this->factory->create($this->getTestedType(), null, $this->getTestOptions());

        $form->submit(null);

        self::assertNull($form->getData());
        self::assertNull($form->getNormData());
        self::assertSame('', $form->getViewData());
        self::assertTrue($form->isSynchronized());
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'empty', $expectedData = null)
    {
        $emptyData = (Suit::Hearts)->value;

        $form = $this->factory->create($this->getTestedType(), null, [
            'class' => Suit::class,
            'empty_data' => $emptyData,
        ]);

        $form->submit(null);

        self::assertSame($emptyData, $form->getViewData());
        self::assertSame(Suit::Hearts, $form->getNormData());
        self::assertSame(Suit::Hearts, $form->getData());
    }

    public function testSubmitMultipleChoiceWithEmptyData()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => true,
            'expanded' => false,
            'class' => Suit::class,
            'empty_data' => [(Suit::Diamonds)->value],
        ]);

        $form->submit(null);

        self::assertSame([Suit::Diamonds], $form->getData());
    }

    public function testSubmitSingleChoiceExpandedWithEmptyData()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => false,
            'expanded' => true,
            'class' => Suit::class,
            'empty_data' => (Suit::Hearts)->value,
        ]);

        $form->submit(null);

        self::assertSame(Suit::Hearts, $form->getData());
    }

    public function testSubmitMultipleChoiceExpandedWithEmptyData()
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => true,
            'expanded' => true,
            'class' => Suit::class,
            'empty_data' => [(Suit::Spades)->value],
        ]);

        $form->submit(null);

        self::assertSame([Suit::Spades], $form->getData());
    }

    /**
     * @dataProvider provideMultiSubmitData
     */
    public function testSubmitMultipleNonExpanded(string $class, array $submittedValues, array $expectedValues)
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => true,
            'expanded' => false,
            'class' => $class,
        ]);

        $form->submit($submittedValues);

        self::assertSame($expectedValues, $form->getData());
        self::assertSame($submittedValues, $form->getViewData());
        self::assertTrue($form->isSynchronized());
    }

    /**
     * @dataProvider provideMultiSubmitData
     */
    public function testSubmitMultipleExpanded(string $class, array $submittedValues, array $expectedValues)
    {
        $form = $this->factory->create($this->getTestedType(), null, [
            'multiple' => true,
            'expanded' => true,
            'class' => $class,
        ]);

        $form->submit($submittedValues);

        self::assertSame($expectedValues, $form->getData());
        self::assertSame($submittedValues, $form->getViewData());
        self::assertTrue($form->isSynchronized());
    }

    public function provideMultiSubmitData(): iterable
    {
        yield 'unbacked' => [
            Answer::class,
            ['0', '1'],
            [Answer::Yes, Answer::No],
        ];

        yield 'string backed' => [
            Suit::class,
            [(Suit::Hearts)->value, (Suit::Spades)->value],
            [Suit::Hearts, Suit::Spades],
        ];

        yield 'integer backed' => [
            Number::class,
            [(string) (Number::Two)->value, (string) (Number::Three)->value],
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

        self::assertSame('Yes', $view->children[0]->vars['label']);
    }

    protected function getTestOptions(): array
    {
        return ['class' => Suit::class];
    }
}
