<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Type;

use Symfony\Component\Form\Event\PostValidateEvent;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Tests\Extension\Core\Type\CollectionTypeTest;
use Symfony\Component\Form\Tests\Extension\Core\Type\FormTypeTest;
use Symfony\Component\Form\Tests\Extension\Core\Type\TextTypeTest;
use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\AuthorType;
use Symfony\Component\Form\Tests\Fixtures\Organization;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Validation;

class FormTypeValidatorExtensionTest extends BaseValidatorExtensionTestCase
{
    public function testSubmitValidatesData()
    {
        $builder = $this->factory->createBuilder(
            FormTypeTest::TESTED_TYPE,
            null,
            [
                'validation_groups' => 'group',
            ]
        );
        $builder->add('firstName', TextType::class, [
            'constraints' => new NotNull(groups: ['group']),
        ]);
        $form = $builder->getForm();

        // specific data is irrelevant
        $form->submit([]);

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isValid());
        $this->assertFalse($form->get('firstName')->isValid());
    }

    public function testValidConstraint()
    {
        $form = $this->createForm(['constraints' => $valid = new Valid()]);

        $this->assertSame([$valid], $form->getConfig()->getOption('constraints'));
    }

    public function testValidConstraintsArray()
    {
        $form = $this->createForm(['constraints' => [$valid = new Valid()]]);

        $this->assertSame([$valid], $form->getConfig()->getOption('constraints'));
    }

    public function testInvalidConstraint()
    {
        $this->expectException(InvalidOptionsException::class);
        $this->createForm(['constraints' => ['foo' => 'bar']]);
    }

    public function testGroupSequenceWithConstraintsOption()
    {
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->create(FormTypeTest::TESTED_TYPE, null, ['validation_groups' => new GroupSequence(['First', 'Second'])])
            ->add('field', TextTypeTest::TESTED_TYPE, [
                'constraints' => [
                    new Length(min: 10, groups: ['First']),
                    new NotBlank(groups: ['Second']),
                ],
            ])
        ;

        $form->submit(['field' => 'wrong']);

        $errors = $form->getErrors(true);

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(Length::class, $errors[0]->getCause()->getConstraint());
    }

    public function testManyFieldsGroupSequenceWithConstraintsOption()
    {
        $formMetadata = new ClassMetadata(Form::class);
        $authorMetadata = (new ClassMetadata(Author::class))
            ->addPropertyConstraint('firstName', new NotBlank(groups: ['Second']))
        ;
        $metadataFactory = $this->createStub(MetadataFactoryInterface::class);
        $metadataFactory
            ->method('getMetadataFor')
            ->willReturnCallback(static function ($classOrObject) use ($formMetadata, $authorMetadata) {
                if (Author::class === $classOrObject || $classOrObject instanceof Author) {
                    return $authorMetadata;
                }

                if (Form::class === $classOrObject || $classOrObject instanceof Form) {
                    return $formMetadata;
                }

                return new ClassMetadata(\is_string($classOrObject) ? $classOrObject : $classOrObject::class);
            })
        ;

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory($metadataFactory)
            ->getValidator()
        ;
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory()
            ->create(FormTypeTest::TESTED_TYPE, new Author(), ['validation_groups' => new GroupSequence(['First', 'Second'])])
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE, [
                'constraints' => [
                    new Length(min: 10, groups: ['First']),
                ],
            ])
            ->add('australian', TextTypeTest::TESTED_TYPE, [
                'constraints' => [
                    new NotBlank(groups: ['Second']),
                ],
            ])
        ;

        $form->submit(['firstName' => '', 'lastName' => 'wrong_1', 'australian' => '']);

        $errors = $form->getErrors(true);

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(Length::class, $errors[0]->getCause()->getConstraint());
        $this->assertSame('children[lastName].data', $errors[0]->getCause()->getPropertyPath());
    }

    public function testInvalidMessage()
    {
        $form = $this->createForm();

        $this->assertEquals('This value is not valid.', $form->getConfig()->getOption('invalid_message'));
    }

    protected function createForm(array $options = [])
    {
        return $this->factory->create(FormTypeTest::TESTED_TYPE, null, $options);
    }

    public function testCollectionTypeKeepAsListOptionFalse()
    {
        $formMetadata = new ClassMetadata(Form::class);
        $authorMetadata = (new ClassMetadata(Author::class))
            ->addPropertyConstraint('firstName', new NotBlank());
        $organizationMetadata = (new ClassMetadata(Organization::class))
            ->addPropertyConstraint('authors', new Valid());
        $metadataFactory = $this->createStub(MetadataFactoryInterface::class);
        $metadataFactory
            ->method('getMetadataFor')
            ->willReturnCallback(static function ($classOrObject) use ($formMetadata, $authorMetadata, $organizationMetadata) {
                if (Author::class === $classOrObject || $classOrObject instanceof Author) {
                    return $authorMetadata;
                }

                if (Organization::class === $classOrObject || $classOrObject instanceof Organization) {
                    return $organizationMetadata;
                }

                if (Form::class === $classOrObject || $classOrObject instanceof Form) {
                    return $formMetadata;
                }

                return new ClassMetadata(\is_string($classOrObject) ? $classOrObject : $classOrObject::class);
            });

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory($metadataFactory)
            ->getValidator();

        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory()
            ->create(FormTypeTest::TESTED_TYPE, new Organization([]), [
                'data_class' => Organization::class,
                'by_reference' => false,
            ])
            ->add('authors', CollectionTypeTest::TESTED_TYPE, [
                'entry_type' => AuthorType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'keep_as_list' => false,
            ])
        ;

        $form->submit([
            'authors' => [
                0 => [
                    'firstName' => '', // Fires a Not Blank Error
                    'lastName' => 'lastName1',
                ],
                // key "1" could be missing if we add 4 blank form entries and then remove it.
                2 => [
                    'firstName' => '', // Fires a Not Blank Error
                    'lastName' => 'lastName3',
                ],
                3 => [
                    'firstName' => '', // Fires a Not Blank Error
                    'lastName' => 'lastName3',
                ],
            ],
        ]);

        // Form does have 3 not blank errors
        $errors = $form->getErrors(true);
        $this->assertCount(3, $errors);

        // Form behaves as expected. It has index 0, 2 and 3 (1 has been removed)
        // But errors property paths mismatch happening with "keep_as_list" option set to false
        $errorPaths = [
            $errors[0]->getCause()->getPropertyPath(),
            $errors[1]->getCause()->getPropertyPath(),
            $errors[2]->getCause()->getPropertyPath(),
        ];

        $this->assertTrue($form->get('authors')->has('0'));
        $this->assertContains('data.authors[0].firstName', $errorPaths);

        $this->assertFalse($form->get('authors')->has('1'));
        $this->assertContains('data.authors[1].firstName', $errorPaths);

        $this->assertTrue($form->get('authors')->has('2'));
        $this->assertContains('data.authors[2].firstName', $errorPaths);

        $this->assertTrue($form->get('authors')->has('3'));
        $this->assertNotContains('data.authors[3].firstName', $errorPaths);

        // As result, root form contain errors
        $this->assertCount(1, $form->getErrors(false));
    }

    public function testCollectionTypeKeepAsListOptionTrue()
    {
        $formMetadata = new ClassMetadata(Form::class);
        $authorMetadata = (new ClassMetadata(Author::class))
            ->addPropertyConstraint('firstName', new Length(1));
        $organizationMetadata = (new ClassMetadata(Organization::class))
            ->addPropertyConstraint('authors', new Valid());
        $metadataFactory = $this->createStub(MetadataFactoryInterface::class);
        $metadataFactory
            ->method('getMetadataFor')
            ->willReturnCallback(static function ($classOrObject) use ($formMetadata, $authorMetadata, $organizationMetadata) {
                if (Author::class === $classOrObject || $classOrObject instanceof Author) {
                    return $authorMetadata;
                }

                if (Organization::class === $classOrObject || $classOrObject instanceof Organization) {
                    return $organizationMetadata;
                }

                if (Form::class === $classOrObject || $classOrObject instanceof Form) {
                    return $formMetadata;
                }

                return new ClassMetadata(\is_string($classOrObject) ? $classOrObject : $classOrObject::class);
            });

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory($metadataFactory)
            ->getValidator();

        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory()
            ->create(FormTypeTest::TESTED_TYPE, new Organization([]), [
                'data_class' => Organization::class,
                'by_reference' => false,
            ])
            ->add('authors', CollectionTypeTest::TESTED_TYPE, [
                'entry_type' => AuthorType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'keep_as_list' => true,
            ])
        ;

        $form->submit([
            'authors' => [
                0 => [
                    'firstName' => 'foobar', // Fires a Length Error
                    'lastName' => 'lastName1',
                ],
                // key "1" could be missing if we add 4 blank form entries and then remove it.
                2 => [
                    'firstName' => 'barfoo', // Fires a Length Error
                    'lastName' => 'lastName3',
                ],
                3 => [
                    'firstName' => 'barbaz', // Fires a Length Error
                    'lastName' => 'lastName3',
                ],
            ],
        ]);

        // Form does have 3 length errors
        $errors = $form->getErrors(true);
        $this->assertCount(3, $errors);

        // No property paths mismatch happening with "keep_as_list" option set to true
        $errorPaths = [
            $errors[0]->getCause()->getPropertyPath(),
            $errors[1]->getCause()->getPropertyPath(),
            $errors[2]->getCause()->getPropertyPath(),
        ];

        $this->assertTrue($form->get('authors')->has('0'));
        $this->assertSame('foobar', $form->get('authors')->get('0')->getData()->firstName);
        $this->assertContains('data.authors[0].firstName', $errorPaths);

        $this->assertTrue($form->get('authors')->has('1'));
        $this->assertSame('barfoo', $form->get('authors')->get('1')->getData()->firstName);
        $this->assertContains('data.authors[1].firstName', $errorPaths);

        $this->assertTrue($form->get('authors')->has('2'));
        $this->assertSame('barbaz', $form->get('authors')->get('2')->getData()->firstName);
        $this->assertContains('data.authors[2].firstName', $errorPaths);

        $this->assertFalse($form->get('authors')->has('3'));
        $this->assertNotContains('data.authors[3].firstName', $errorPaths);

        // Root form does NOT contain errors
        $this->assertCount(0, $form->getErrors(false));
    }

    public function testPostValidateEventIsDispatchedOnEachFormAfterValidation()
    {
        $calls = [];
        $listener = static function (string $key) use (&$calls) {
            return static function (PostValidateEvent $event) use ($key, &$calls) {
                $calls[] = [$key, $event->getForm()->getName(), $event->getForm()->getRoot()->isValid()];
            };
        };

        $builder = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->createBuilder(FormTypeTest::TESTED_TYPE)
            ->addEventListener(FormEvents::POST_VALIDATE, $listener('root'));
        $builder->add('name', TextTypeTest::TESTED_TYPE, ['constraints' => new NotBlank()]);
        $builder->get('name')->addEventListener(FormEvents::POST_VALIDATE, $listener('name'));
        $builder->add('sub', FormTypeTest::TESTED_TYPE);
        $builder->get('sub')->add('deep', TextTypeTest::TESTED_TYPE);
        $builder->get('sub')->addEventListener(FormEvents::POST_VALIDATE, $listener('sub'));
        $builder->get('sub')->get('deep')->addEventListener(FormEvents::POST_VALIDATE, $listener('deep'));
        $builder->add('save', SubmitType::class);

        $form = $builder->getForm();
        $form->submit(['name' => '', 'sub' => ['deep' => 'foo'], 'save' => '']);

        $this->assertFalse($form->isValid());
        $this->assertSame([
            ['name', 'name', false],
            ['deep', 'deep', false],
            ['sub', 'sub', false],
            ['root', 'form', false],
        ], $calls);
    }

    public function testPostValidateEventListenersSeeAValidFormTree()
    {
        $calls = [];
        $listener = static function (string $key) use (&$calls) {
            return static function (PostValidateEvent $event) use ($key, &$calls) {
                $calls[] = [$key, $event->getForm()->getRoot()->isValid(), $event->getData()];
            };
        };

        $builder = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->createBuilder(FormTypeTest::TESTED_TYPE)
            ->addEventListener(FormEvents::POST_VALIDATE, $listener('root'));
        $builder->add('name', TextTypeTest::TESTED_TYPE, ['constraints' => new NotBlank()]);
        $builder->get('name')->addEventListener(FormEvents::POST_VALIDATE, $listener('name'));

        $form = $builder->getForm();
        $form->submit(['name' => 'foo']);

        $this->assertTrue($form->isValid());
        $this->assertSame([
            ['name', true, 'foo'],
            ['root', true, ['name' => 'foo']],
        ], $calls);
    }

    public function testErrorsAddedByPostValidateEventListenersAreSeenByParentListeners()
    {
        $rootIsValid = null;

        $builder = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->createBuilder(FormTypeTest::TESTED_TYPE)
            ->addEventListener(FormEvents::POST_VALIDATE, static function (PostValidateEvent $event) use (&$rootIsValid) {
                $rootIsValid = $event->getForm()->isValid();
            });
        $builder->add('name', TextTypeTest::TESTED_TYPE);
        $builder->get('name')->addEventListener(FormEvents::POST_VALIDATE, static function (PostValidateEvent $event) {
            $event->getForm()->addError(new FormError('invalid'));
        });

        $form = $builder->getForm();
        $form->submit(['name' => 'foo']);

        $this->assertFalse($rootIsValid);
        $this->assertFalse($form->isValid());
        $this->assertFalse($form->get('name')->isValid());
    }

    public function testPostValidateEventIsNotDispatchedOnUnsubmittedForms()
    {
        $calls = [];
        $listener = static function (string $key) use (&$calls) {
            return static function () use ($key, &$calls) {
                $calls[] = $key;
            };
        };

        $builder = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->createBuilder(FormTypeTest::TESTED_TYPE)
            ->addEventListener(FormEvents::POST_VALIDATE, $listener('root'));
        $builder->add('name', TextTypeTest::TESTED_TYPE);
        $builder->get('name')->addEventListener(FormEvents::POST_VALIDATE, $listener('name'));
        $builder->add('other', TextTypeTest::TESTED_TYPE);
        $builder->get('other')->addEventListener(FormEvents::POST_VALIDATE, $listener('other'));

        $form = $builder->getForm();
        $form->submit(['name' => 'foo'], false);

        $this->assertSame(['name', 'root'], $calls);
    }

    public function testPostValidateEventIsDispatchedWhenOnlyAChildListens()
    {
        $calls = [];

        $builder = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->createBuilder(FormTypeTest::TESTED_TYPE);
        $builder->add('name', TextTypeTest::TESTED_TYPE);
        $builder->get('name')->addEventListener(FormEvents::POST_VALIDATE, static function (PostValidateEvent $event) use (&$calls) {
            $calls[] = $event->getForm()->getName();
        });

        $form = $builder->getForm();
        $form->submit(['name' => 'foo']);

        $this->assertSame(['name'], $calls);
    }

    public function testPostValidateEventDataCannotBeChanged()
    {
        $builder = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->createBuilder(FormTypeTest::TESTED_TYPE)
            ->addEventListener(FormEvents::POST_VALIDATE, static function (PostValidateEvent $event) {
                $event->setData([]);
            });
        $form = $builder->getForm();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Form data cannot be changed during "form.post_validate", you should use "form.pre_submit" or "form.submit" instead.');

        $form->submit([]);
    }
}
