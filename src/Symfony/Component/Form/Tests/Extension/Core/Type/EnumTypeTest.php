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
use Symfony\Component\Form\Tests\Extension\Core\Type\BaseTypeTestCase;
use Symfony\Component\Form\Tests\Fixtures\Answer;
use Symfony\Component\Form\Tests\Fixtures\Number;
use Symfony\Component\Form\Tests\Fixtures\Suit;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

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

        $this->assertEquals($expectedData, $form->getData());
        $this->assertEquals($submittedData, $form->getViewData());
        $this->assertTrue($form->isSynchronized());
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

        $this->assertSame($expectedValues, $form->getData());
        $this->assertSame($submittedValues, $form->getViewData());
        $this->assertTrue($form->isSynchronized());
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

    protected function getTestOptions(): array
    {
        return ['class' => Suit::class];
    }
}
