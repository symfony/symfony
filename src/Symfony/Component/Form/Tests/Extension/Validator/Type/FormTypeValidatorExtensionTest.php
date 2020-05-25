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

use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;
use Symfony\Component\Form\Tests\Extension\Core\Type\FormTypeTest;
use Symfony\Component\Form\Tests\Extension\Core\Type\TextTypeTest;
use Symfony\Component\Form\Tests\Fixtures\Author;
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
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
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

    protected function createForm(array $options = [])
    {
        return $this->factory->create(FormTypeTest::TESTED_TYPE, null, $options);
    }
}
