<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Component\Form\Tests\Fixtures\TypedProperties;
use Symfony\Component\Form\Tests\Fixtures\WriteTargetTypedProperties;

class EmptyDataGuessingTest extends FormIntegrationTestCase
{
    public array $typeGuesses = [];

    public function testSubmitEmptyStringOnNonNullableProperty()
    {
        $data = new TypedProperties();
        $form = $this->createForm($data, 'name');

        $form->submit(['name' => '']);

        $this->assertSame('', $data->name);
    }

    public function testSubmitMissingFieldOnNonNullableProperty()
    {
        $data = new TypedProperties();
        $form = $this->createForm($data, 'name');

        $form->submit([]);

        $this->assertSame('', $data->name);
    }

    #[DataProvider('provideGuessedEmptyData')]
    public function testGuessedEmptyData(string $property, mixed $expected)
    {
        $form = $this->createForm(new TypedProperties(), $property);

        $this->assertSame($expected, $form->get($property)->getConfig()->getEmptyData());
    }

    public static function provideGuessedEmptyData(): iterable
    {
        yield 'string' => ['name', ''];
        yield 'int' => ['age', '0'];
        yield 'float' => ['height', '0'];
        yield 'bool' => ['active', false];
    }

    #[DataProvider('provideNotGuessedEmptyData')]
    public function testNotGuessedEmptyData(string $property)
    {
        $form = $this->createForm(new TypedProperties(), $property);

        $this->assertInstanceOf(\Closure::class, $form->get($property)->getConfig()->getEmptyData());
    }

    public static function provideNotGuessedEmptyData(): iterable
    {
        yield 'nullable' => ['nickname'];
        yield 'nullable mutator' => ['slug'];
        yield 'array' => ['tags'];
        yield 'mixed' => ['extra'];
        yield 'union' => ['identifier'];
    }

    public function testExplicitEmptyDataWins()
    {
        $data = new TypedProperties();
        $form = $this->createForm($data, 'name', options: ['empty_data' => 'default']);

        $form->submit(['name' => '']);

        $this->assertSame('default', $data->name);
    }

    public function testUnmappedFieldsAreNotGuessed()
    {
        $form = $this->createForm(new TypedProperties(), 'name', options: ['mapped' => false]);

        $this->assertInstanceOf(\Closure::class, $form->get('name')->getConfig()->getEmptyData());
    }

    public function testFieldsWithAPropertyPathAreNotGuessed()
    {
        $form = $this->createForm(new TypedProperties(), 'name', options: ['property_path' => 'name']);

        $this->assertInstanceOf(\Closure::class, $form->get('name')->getConfig()->getEmptyData());
    }

    public function testExplicitlyTypedFieldsAreNotGuessed()
    {
        $form = $this->createForm(new TypedProperties(), 'name', TextType::class);

        $this->assertInstanceOf(\Closure::class, $form->get('name')->getConfig()->getEmptyData());
    }

    public function testGuessedIntegerTypeIsSubmittedAsZero()
    {
        $this->typeGuesses['age'] = new TypeGuess(IntegerType::class, [], Guess::HIGH_CONFIDENCE);

        $data = new TypedProperties();
        $form = $this->createForm($data, 'age');

        $form->submit(['age' => '']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame(0, $data->age);
    }

    public function testUncheckedCheckboxKeepsReturningFalse()
    {
        $this->typeGuesses['active'] = new TypeGuess(CheckboxType::class, [], Guess::HIGH_CONFIDENCE);

        $data = new TypedProperties();
        $data->active = true;
        $form = $this->createForm($data, 'active');

        $form->submit([]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($data->active);
    }

    public function testConstructorArgumentIsNotAWriteTarget()
    {
        $data = new WriteTargetTypedProperties();
        $form = $this->createForm($data, 'qty');

        $form->submit(['qty' => '']);

        $this->assertTrue($form->isSynchronized());
        $this->assertNull($data->qty);
    }

    public function testAccessorIsNotAWriteTarget()
    {
        $data = new WriteTargetTypedProperties();
        $data->name = 'foo';
        $form = $this->createForm($data, 'name');

        $form->submit(['name' => '']);

        $this->assertNull($data->name);
    }

    public function testMutatorWinsOverProperty()
    {
        $data = new WriteTargetTypedProperties();
        $form = $this->createForm($data, 'label');

        $this->assertInstanceOf(\Closure::class, $form->get('label')->getConfig()->getEmptyData());

        $data->label = 'foo';
        $form->submit(['label' => '']);

        $this->assertSame('', $data->label);
    }

    public function testMutatorOfANullablePropertyIsGuessed()
    {
        $data = new WriteTargetTypedProperties();
        $form = $this->createForm($data, 'title');

        $this->assertSame('', $form->get('title')->getConfig()->getEmptyData());

        $form->submit(['title' => '']);

        $this->assertSame('', $data->getTitle());
    }

    public function testSetHookIsTheWriteTarget()
    {
        $data = new WriteTargetTypedProperties();
        $form = $this->createForm($data, 'comment');

        $this->assertInstanceOf(\Closure::class, $form->get('comment')->getConfig()->getEmptyData());

        $form->submit(['comment' => '']);

        $this->assertSame('none', $data->comment);
    }

    #[DataProvider('provideNonPublicWriteTargets')]
    public function testNonPublicWriteTargetsAreNotGuessed(string $property)
    {
        $form = $this->createForm(new WriteTargetTypedProperties(), $property);

        $this->assertInstanceOf(\Closure::class, $form->get($property)->getConfig()->getEmptyData());
    }

    public static function provideNonPublicWriteTargets(): iterable
    {
        yield 'private(set) property' => ['slug'];
        yield 'protected(set) property' => ['tone'];
        yield 'readonly property' => ['ref'];
        yield 'virtual property without a set hook' => ['alias'];
        yield 'non-public mutator' => ['thing'];
    }

    public function testPublicWriteTargetIsStillGuessed()
    {
        $data = new WriteTargetTypedProperties();
        $form = $this->createForm($data, 'heading');

        $this->assertSame('', $form->get('heading')->getConfig()->getEmptyData());

        $form->submit(['heading' => '']);

        $this->assertSame('', $data->heading);
    }

    protected function getTypeGuessers(): array
    {
        return [new class($this) implements FormTypeGuesserInterface {
            public function __construct(private EmptyDataGuessingTest $test)
            {
            }

            public function guessType(string $class, string $property): ?TypeGuess
            {
                return $this->test->typeGuesses[$property] ?? null;
            }

            public function guessRequired(string $class, string $property): ?ValueGuess
            {
                return null;
            }

            public function guessMaxLength(string $class, string $property): ?ValueGuess
            {
                return null;
            }

            public function guessPattern(string $class, string $property): ?ValueGuess
            {
                return null;
            }
        }];
    }

    private function createForm(object $data, string $property, ?string $type = null, array $options = []): FormInterface
    {
        return $this->factory
            ->createBuilder(options: ['data_class' => $data::class, 'data' => $data])
            ->add($property, $type, $options)
            ->getForm();
    }
}
