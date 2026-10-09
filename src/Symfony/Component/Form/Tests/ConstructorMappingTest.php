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
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Component\Form\Tests\Fixtures\ConstructorMapping\Account;
use Symfony\Component\Form\Tests\Fixtures\ConstructorMapping\OptionalArguments;
use Symfony\Component\Form\Tests\Fixtures\ConstructorMapping\ReadonlyAddress;
use Symfony\Component\Form\Tests\Fixtures\ConstructorMapping\ReadonlyEntity;
use Symfony\Component\Form\Tests\Fixtures\ConstructorMapping\ReadonlyPerson;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;

class ConstructorMappingTest extends FormIntegrationTestCase
{
    public function testSubmitCreatesTheDataThroughTheConstructor()
    {
        $form = $this->createPersonBuilder()
            ->add('birthDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true])
            ->getForm();

        $form->submit(['name' => 'Bernhard', 'birthDate' => '2020-01-02', 'tags' => ['a', 'b']]);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals(new ReadonlyPerson('Bernhard', new \DateTimeImmutable('2020-01-02'), ['a', 'b']), $form->getData());
    }

    public function testArgumentsWithoutAFieldGetTheirDefaultValue()
    {
        $form = $this->createPersonBuilder()->getForm();

        $form->submit(['name' => 'Bernhard']);

        $this->assertEquals(new ReadonlyPerson('Bernhard'), $form->getData());
    }

    public function testFieldsAreMatchedToArgumentsByPropertyPath()
    {
        $form = $this->factory->createBuilder(FormType::class, null, ['data_class' => ReadonlyPerson::class])
            ->add('fullName', TextType::class, ['property_path' => 'name'])
            ->getForm();

        $form->submit(['fullName' => 'Bernhard']);

        $this->assertEquals(new ReadonlyPerson('Bernhard'), $form->getData());
    }

    #[DataProvider('provideIgnoredFieldOptions')]
    public function testIgnoredFieldsAreNotPassedToTheConstructor(array $options)
    {
        $form = $this->createPersonBuilder()
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true] + $options)
            ->getForm();

        $form->submit(['name' => 'Bernhard', 'tags' => ['a']]);

        $this->assertEquals(new ReadonlyPerson('Bernhard'), $form->getData());
    }

    public static function provideIgnoredFieldOptions(): iterable
    {
        yield 'unmapped' => [['mapped' => false]];
        yield 'disabled' => [['disabled' => true]];
    }

    public function testUnsubmittedFieldsAreNotPassedToTheConstructor()
    {
        $form = $this->createPersonBuilder()
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'data' => ['a']])
            ->getForm();

        $form->submit(['name' => 'Bernhard'], false);

        $this->assertEquals(new ReadonlyPerson('Bernhard'), $form->getData());
    }

    public function testFieldsNotWrittenByReferenceAreNotWrittenAgainAfterTheConstructor()
    {
        $form = $this->createPersonBuilder()
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true, 'by_reference' => false])
            ->getForm();

        $form->submit(['name' => 'Bernhard', 'tags' => ['a']]);

        $this->assertEquals(new ReadonlyPerson('Bernhard', null, ['a']), $form->getData());
    }

    public function testFieldsOfAFormInheritingDataArePassedToTheConstructor()
    {
        $builder = $this->factory->createBuilder(FormType::class, null, ['data_class' => ReadonlyPerson::class]);
        $builder->add($builder->create('identity', FormType::class, ['inherit_data' => true])->add('name', TextType::class));
        $form = $builder->getForm();

        $form->submit(['identity' => ['name' => 'Bernhard']]);

        $this->assertEquals(new ReadonlyPerson('Bernhard'), $form->getData());
    }

    public function testNestedDataIsCreatedThroughTheConstructor()
    {
        $builder = $this->createPersonBuilder();
        $builder->add($builder->create('address', FormType::class, ['data_class' => ReadonlyAddress::class])->add('city', TextType::class));
        $form = $builder->getForm();

        $form->submit(['name' => 'Bernhard', 'address' => ['city' => 'Vienna']]);

        $this->assertEquals(new ReadonlyPerson('Bernhard', null, [], new ReadonlyAddress('Vienna')), $form->getData());
    }

    public function testEmptyNestedDataIsNullWhenNotRequired()
    {
        $builder = $this->createPersonBuilder();
        $builder->add($builder->create('address', FormType::class, ['data_class' => ReadonlyAddress::class, 'required' => false])->add('city', TextType::class));
        $form = $builder->getForm();

        $form->submit(['name' => 'Bernhard', 'address' => ['city' => '']]);

        $this->assertEquals(new ReadonlyPerson('Bernhard'), $form->getData());
    }

    public function testNonPromotedArgumentsArePassedToTheConstructor()
    {
        $form = $this->factory->createBuilder(FormType::class, null, ['data_class' => Account::class])
            ->add('username', TextType::class)
            ->getForm();

        $form->submit(['username' => 'bernhard']);

        $this->assertEquals(new Account('bernhard'), $form->getData());
    }

    public function testSettersAreCalledWhenNoArgumentIsRequiredOrReadonly()
    {
        $form = $this->factory->createBuilder(FormType::class, null, ['data_class' => OptionalArguments::class])
            ->add('name', TextType::class)
            ->getForm();

        $form->submit(['name' => 'bernhard']);

        $this->assertSame('BERNHARD', $form->getData()->getName());
    }

    public function testRequiredArgumentWithoutAFieldThrows()
    {
        $form = $this->factory->createBuilder(FormType::class, null, ['data_class' => ReadonlyAddress::class])
            ->add('zipCode', TextType::class)
            ->getForm();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot create "Symfony\Component\Form\Tests\Fixtures\ConstructorMapping\ReadonlyAddress": no mapped field matches the "$city" argument of its constructor. Add such a field or set the "empty_data" option.');

        $form->submit(['zipCode' => '1010']);
    }

    public function testRequiredArgumentOfAnUnsubmittedFieldFailsTheTransformation()
    {
        $form = $this->createPersonBuilder()->getForm();

        $form->submit([], false);

        $this->assertFalse($form->isSynchronized());
        $this->assertNull($form->getData());
        $this->assertSame('No value was submitted for the "$name" argument of "Symfony\Component\Form\Tests\Fixtures\ConstructorMapping\ReadonlyPerson::__construct()".', $form->getTransformationFailure()->getMessage());
    }

    public function testRequiredArgumentOfAnUnsynchronizedFieldFailsTheTransformation()
    {
        $builder = $this->factory->createBuilder(FormType::class, null, ['data_class' => ReadonlyPerson::class]);
        $builder->add($builder->create('name', TextType::class)->addViewTransformer(new CallbackTransformer(static fn ($value) => $value, static fn () => throw new TransformationFailedException())));
        $form = $builder->getForm();

        $form->submit(['name' => 'Bernhard']);

        $this->assertFalse($form->get('name')->isSynchronized());
        $this->assertFalse($form->isSynchronized());
        $this->assertNull($form->getData());
    }

    public function testNullForANonNullableArgumentFailsTheTransformation()
    {
        $form = $this->createPersonBuilder()->getForm();

        $form->submit(['name' => '']);

        $this->assertFalse($form->isSynchronized());
        $this->assertNull($form->getData());
        $this->assertSame('The "$name" argument of "Symfony\Component\Form\Tests\Fixtures\ConstructorMapping\ReadonlyPerson::__construct()" does not accept null.', $form->getTransformationFailure()->getMessage());
    }

    public function testNullForANonNullableArgumentGetsItsDefaultValue()
    {
        $form = $this->createAddressForm();

        $form->submit(['city' => 'Vienna', 'zipCode' => '']);

        $this->assertEquals(new ReadonlyAddress('Vienna'), $form->getData());
    }

    public function testSubmitReplacesTheDataWhenAReadonlyPropertyChanges()
    {
        $person = new ReadonlyPerson('Bernhard', new \DateTimeImmutable('2020-01-02'), ['a']);
        $form = $this->createPersonBuilder($person)
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true, 'allow_delete' => true])
            ->getForm();

        $form->submit(['name' => 'Fabien', 'tags' => ['b']]);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals(new ReadonlyPerson('Fabien', new \DateTimeImmutable('2020-01-02'), ['b']), $form->getData());
        $this->assertEquals(new ReadonlyPerson('Bernhard', new \DateTimeImmutable('2020-01-02'), ['a']), $person);
    }

    public function testSubmitKeepsTheDataWhenNothingChanges()
    {
        $person = new ReadonlyPerson('Bernhard', new \DateTimeImmutable('2020-01-02'), ['a']);
        $form = $this->createPersonBuilder($person)
            ->add('birthDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'by_reference' => false])
            ->getForm();

        $form->submit(['name' => 'Bernhard', 'birthDate' => '2020-01-02', 'tags' => ['a']]);

        $this->assertSame($person, $form->getData());
    }

    public function testUnsubmittedFieldsKeepTheirValueWhenTheDataIsReplaced()
    {
        $form = $this->createPersonBuilder(new ReadonlyPerson('Bernhard', null, ['a']))
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class])
            ->getForm();

        $form->submit(['name' => 'Fabien'], false);

        $this->assertEquals(new ReadonlyPerson('Fabien', null, ['a']), $form->getData());
    }

    public function testNestedDataIsReplacedWhenItChanges()
    {
        $builder = $this->createPersonBuilder(new ReadonlyPerson('Bernhard', null, [], new ReadonlyAddress('Vienna', '1010')));
        $builder->add($builder->create('address', FormType::class, ['data_class' => ReadonlyAddress::class])->add('city', TextType::class));
        $form = $builder->getForm();

        $form->submit(['name' => 'Bernhard', 'address' => ['city' => 'Paris']]);

        $this->assertEquals(new ReadonlyPerson('Bernhard', null, [], new ReadonlyAddress('Paris', '1010')), $form->getData());
    }

    public function testNullForANonNullableArgumentFailsTheTransformationWhenTheDataIsReplaced()
    {
        $form = $this->createPersonBuilder(new ReadonlyPerson('Bernhard'))->getForm();

        $form->submit(['name' => '']);

        $this->assertFalse($form->isSynchronized());
        $this->assertNull($form->getData());
    }

    public function testNullForANonNullableArgumentGetsItsDefaultValueWhenTheDataIsReplaced()
    {
        $form = $this->createAddressForm(new ReadonlyAddress('Vienna', '1010'));

        $form->submit(['city' => 'Vienna', 'zipCode' => '']);

        $this->assertEquals(new ReadonlyAddress('Vienna'), $form->getData());
    }

    public function testNullForANonNullableArgumentWithItsDefaultValueKeepsTheData()
    {
        $address = new ReadonlyAddress('Vienna');
        $form = $this->createAddressForm($address);

        $form->submit(['city' => 'Vienna', 'zipCode' => '']);

        $this->assertSame($address, $form->getData());
    }

    public function testDataWithStateOutsideTheConstructorIsNotReplaced()
    {
        $form = $this->factory->createBuilder(FormType::class, new ReadonlyEntity('Bernhard'), ['data_class' => ReadonlyEntity::class])
            ->add('name', TextType::class)
            ->getForm();

        $this->expectException(NoSuchPropertyException::class);

        $form->submit(['name' => 'Fabien']);
    }

    public function testDataWithStateOutsideTheConstructorIsCreatedThroughTheConstructor()
    {
        $form = $this->factory->createBuilder(FormType::class, null, ['data_class' => ReadonlyEntity::class])
            ->add('name', TextType::class, ['by_reference' => false])
            ->getForm();

        $form->submit(['name' => 'Bernhard']);

        $this->assertEquals(new ReadonlyEntity('Bernhard'), $form->getData());
    }

    private function createAddressForm(?ReadonlyAddress $data = null): FormInterface
    {
        return $this->factory->createBuilder(FormType::class, $data, ['data_class' => ReadonlyAddress::class])
            ->add('city', TextType::class)
            ->add('zipCode', TextType::class)
            ->getForm();
    }

    private function createPersonBuilder(?ReadonlyPerson $data = null): FormBuilderInterface
    {
        return $this->factory->createBuilder(FormType::class, $data, ['data_class' => ReadonlyPerson::class])
            ->add('name', TextType::class);
    }
}
