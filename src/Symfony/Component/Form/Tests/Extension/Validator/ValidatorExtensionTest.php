<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\Constraints\Form as FormConstraint;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorTypeGuesser;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\Mapping\TraversalStrategy;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\Validation;

class ValidatorExtensionTest extends TestCase
{
    public function test2Dot5ValidationApi()
    {
        $metadata = new ClassMetadata(Form::class);

        $metadataFactory = new FakeMetadataFactory();
        $metadataFactory->addMetadata($metadata);

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory($metadataFactory)
            ->getValidator();

        $extension = new ValidatorExtension($validator);

        $this->assertInstanceOf(ValidatorTypeGuesser::class, $extension->loadTypeGuesser());

        $this->assertCount(1, $metadata->getConstraints());
        $this->assertInstanceOf(FormConstraint::class, $metadata->getConstraints()[0]);

        $this->assertSame(CascadingStrategy::NONE, $metadata->cascadingStrategy);
        $this->assertSame(TraversalStrategy::IMPLICIT, $metadata->traversalStrategy);
        $this->assertSame(CascadingStrategy::CASCADE, $metadata->getPropertyMetadata('children')[0]->cascadingStrategy);
        $this->assertSame(TraversalStrategy::IMPLICIT, $metadata->getPropertyMetadata('children')[0]->traversalStrategy);
    }

    public function testDataConstraintsInvalidateFormEvenIfFieldIsNotSubmitted()
    {
        $form = $this->createForm(FooType::class);
        $form->submit(['baz' => 'foobar'], false);

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isValid());
        $this->assertFalse($form->get('bar')->isSubmitted());
        $this->assertCount(1, $form->get('bar')->getErrors());
    }

    public function testFieldConstraintsDoNotInvalidateFormIfFieldIsNotSubmitted()
    {
        $form = $this->createForm(FooType::class);
        $form->submit(['bar' => 'foobar'], false);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());
    }

    public function testFieldConstraintsInvalidateFormIfFieldIsSubmitted()
    {
        $form = $this->createForm(FooType::class);
        $form->submit(['bar' => 'foobar', 'baz' => ''], false);

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isValid());
        $this->assertTrue($form->get('bar')->isSubmitted());
        $this->assertTrue($form->get('bar')->isValid());
        $this->assertTrue($form->get('baz')->isSubmitted());
        $this->assertFalse($form->get('baz')->isValid());
    }

    public function testFieldsValidateInSequence()
    {
        $allowEmptyString = property_exists(Length::class, 'allowEmptyString') ? ['allowEmptyString' => true] : [];

        $form = $this->createForm(FormType::class, null, [
            'validation_groups' => new GroupSequence(['group1', 'group2']),
        ])
            ->add('foo', TextType::class, [
                'constraints' => [new Length(['min' => 10, 'groups' => ['group1']] + $allowEmptyString)],
            ])
            ->add('bar', TextType::class, [
                'constraints' => [new NotBlank(['groups' => ['group2']])],
            ])
        ;

        $form->submit(['foo' => 'invalid', 'bar' => null]);

        $errors = $form->getErrors(true);

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(Length::class, $errors[0]->getCause()->getConstraint());
    }

    public function testFieldsValidateInSequenceWithNestedGroupsArray()
    {
        $allowEmptyString = property_exists(Length::class, 'allowEmptyString') ? ['allowEmptyString' => true] : [];

        $form = $this->createForm(FormType::class, null, [
            'validation_groups' => new GroupSequence([['group1', 'group2'], 'group3']),
        ])
            ->add('foo', TextType::class, [
                'constraints' => [new Length(['min' => 10, 'groups' => ['group1']] + $allowEmptyString)],
            ])
            ->add('bar', TextType::class, [
                'constraints' => [new Length(['min' => 10, 'groups' => ['group2']] + $allowEmptyString)],
            ])
            ->add('baz', TextType::class, [
                'constraints' => [new NotBlank(['groups' => ['group3']])],
            ])
        ;

        $form->submit(['foo' => 'invalid', 'bar' => 'invalid', 'baz' => null]);

        $errors = $form->getErrors(true);

        $this->assertCount(2, $errors);
        $this->assertInstanceOf(Length::class, $errors[0]->getCause()->getConstraint());
        $this->assertInstanceOf(Length::class, $errors[1]->getCause()->getConstraint());
    }

    private function createForm($type, $data = null, array $options = [])
    {
        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory(new LazyLoadingMetadataFactory(new StaticMethodLoader()))
            ->getValidator();
        $formFactoryBuilder = new FormFactoryBuilder();
        $formFactoryBuilder->addExtension(new ValidatorExtension($validator));
        $formFactory = $formFactoryBuilder->getFormFactory();

        return $formFactory->create($type, $data, $options);
    }
}

class Foo
{
    public $bar;
    public $baz;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('bar', new NotBlank());
    }
}

class FooType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('bar')
            ->add('baz', null, [
                'constraints' => [new NotBlank()],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', Foo::class);
    }
}
