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

use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;
use Symfony\Component\Form\Tests\Extension\Core\Type\CollectionTypeTest;
use Symfony\Component\Form\Tests\Extension\Core\Type\FormTypeTest;
use Symfony\Component\Form\Tests\Extension\Core\Type\TextTypeTest;
use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\AuthorType;
use Symfony\Component\Form\Tests\Fixtures\Organization;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Validation;

class FormTypeValidatorExtensionTest extends BaseValidatorExtensionTest
{
    use ExpectDeprecationTrait;
    use ValidatorExtensionTrait;

    public function testSubmitValidatesData()
    {
        $builder = $this->factory->createBuilder(
            FormTypeTest::TESTED_TYPE,
            null,
            [
                'validation_groups' => 'group',
            ]
        );
        $builder->add('firstName', FormTypeTest::TESTED_TYPE);
        $form = $builder->getForm();

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($this->equalTo($form))
            ->willReturn(new ConstraintViolationList());

        // specific data is irrelevant
        $form->submit([]);
    }

    public function testValidConstraint()
    {
        $form = $this->createForm(['constraints' => $valid = new Valid()]);

        $this->assertSame([$valid], $form->getConfig()->getOption('constraints'));
    }

    public function testGroupSequenceWithConstraintsOption()
    {
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator(), false))
            ->getFormFactory()
            ->create(FormTypeTest::TESTED_TYPE, null, (['validation_groups' => new GroupSequence(['First', 'Second'])]))
            ->add('field', TextTypeTest::TESTED_TYPE, [
                'constraints' => [
                    new Length(['min' => 10, 'groups' => ['First']]),
                    new NotBlank(['groups' => ['Second']]),
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
            ->addPropertyConstraint('firstName', new NotBlank(['groups' => 'Second']))
        ;
        $metadataFactory = $this->createMock(MetadataFactoryInterface::class);
        $metadataFactory->expects($this->any())
            ->method('getMetadataFor')
            ->willReturnCallback(static function ($classOrObject) use ($formMetadata, $authorMetadata) {
                if (Author::class === $classOrObject || $classOrObject instanceof Author) {
                    return $authorMetadata;
                }

                if (Form::class === $classOrObject || $classOrObject instanceof Form) {
                    return $formMetadata;
                }

                return new ClassMetadata(\is_string($classOrObject) ? $classOrObject : \get_class($classOrObject));
            })
        ;

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory($metadataFactory)
            ->getValidator()
        ;
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory()
            ->create(FormTypeTest::TESTED_TYPE, new Author(), (['validation_groups' => new GroupSequence(['First', 'Second'])]))
            ->add('firstName', TextTypeTest::TESTED_TYPE)
            ->add('lastName', TextTypeTest::TESTED_TYPE, [
                'constraints' => [
                    new Length(['min' => 10, 'groups' => ['First']]),
                ],
            ])
            ->add('australian', TextTypeTest::TESTED_TYPE, [
                'constraints' => [
                    new NotBlank(['groups' => ['Second']]),
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

    /**
     * @group legacy
     */
    public function testLegacyInvalidMessage()
    {
        $this->expectDeprecation('Since symfony/form 5.2: Setting the "legacy_error_messages" option to "true" is deprecated. It will be disabled in Symfony 6.0.');

        $form = $this->createForm(['legacy_error_messages' => true]);

        $this->assertEquals('This value is not valid.', $form->getConfig()->getOption('invalid_message'));
    }

    protected function createForm(array $options = [])
    {
        return $this->factory->create(FormTypeTest::TESTED_TYPE, null, $options);
    }

    public function testErrorPathOnCollections()
    {
        $formMetadata = new ClassMetadata(Form::class);
        $authorMetadata = (new ClassMetadata(Author::class))
            ->addPropertyConstraint('firstName', new NotBlank());
        $organizationMetadata = (new ClassMetadata(Organization::class))
            ->addPropertyConstraint('authors', new Valid());
        $metadataFactory = $this->createMock(MetadataFactoryInterface::class);
        $metadataFactory->expects($this->any())
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

                return new ClassMetadata(\is_string($classOrObject) ? $classOrObject : \get_class($classOrObject));
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

        //Form behaves right (...?). It has index 0, 2 and 3 (1 has been removed)
        $this->assertTrue($form->get('authors')->has('0'));
        $this->assertFalse($form->get('authors')->has('1'));
        $this->assertTrue($form->get('authors')->has('2'));
        $this->assertTrue($form->get('authors')->has('3'));

        //Form does have 3 not blank errors
        $errors = $form->getErrors(true);
        $this->assertCount(3, $errors);

        //But errors property paths are messing up
        $errorPaths = [
            $errors[0]->getCause()->getPropertyPath(),
            $errors[1]->getCause()->getPropertyPath(),
            $errors[2]->getCause()->getPropertyPath(),
        ];

        $this->assertContains('data.authors[0].firstName', $errorPaths);
        $this->assertNotContains('data.authors[1].firstName', $errorPaths);
        $this->assertContains('data.authors[2].firstName', $errorPaths);
        $this->assertContains('data.authors[3].firstName', $errorPaths);

        //In fact, root form should NOT contain errors but it does
        $this->assertCount(0, $form->getErrors(false));
    }
}
