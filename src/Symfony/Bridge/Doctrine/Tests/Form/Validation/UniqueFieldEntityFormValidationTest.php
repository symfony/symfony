<?php

namespace Symfony\Bridge\Doctrine\Tests\Form\Validation;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;
use Symfony\Bridge\Doctrine\Tests\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\UniqueFieldFormValidationEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\UniqueGroupFieldsEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapper;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Tests\Extension\Core\Type\BaseTypeTestCase;
use Symfony\Component\Form\Tests\Extension\Core\Type\FormTypeTest;
use Symfony\Component\Form\Tests\Extension\Core\Type\TextTypeTest;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Form\Test\TypeTestCase;

class UniqueFieldEntityFormValidationTest extends TypeTestCase
{
    private EntityManager $em;
    private MockObject&ManagerRegistry $emRegistry;

    protected function setUp(): void
    {
        $this->em = DoctrineTestHelper::createTestEntityManager();
        $this->emRegistry = $this->createRegistryMock('default', $this->em);

        parent::setUp();
    }

    protected function createRegistryMock($name, $em): MockObject&ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->any())
            ->method('getManager')
            ->with($this->equalTo($name))
            ->willReturn($em);

        return $registry;
    }
    protected function getExtensions(): array
    {
        $factory = new ConstraintValidatorFactory([
            'doctrine.orm.validator.unique' => new UniqueEntityValidator($this->emRegistry)
        ]);

        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory($factory)
            ->enableAttributeMapping()
            ->getValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testFormValidationForEntityWithUniqueFieldNotValid()
    {
        $entity1 = new UniqueFieldFormValidationEntity(1, 'Foo');

        $form = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, ['data_class' => UniqueFieldFormValidationEntity::class])
            ->add('name', TextTypeTest::TESTED_TYPE)
            ->add('token', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $constraintViolation = new ConstraintViolation(
            'This value should not be used.',
            'This value should not be used.',
            [
                '{{ value }}' => 'myNameValue',
                '{{ name value }}' => '"myNameValue"',
            ],
            $form,
            'data.name',
            'myNameValue',
            null,
            'code',
            new UniqueEntity(
                ['name']
            ),
            $entity1
        );

        $violationMapper = new ViolationMapper();
        $violationMapper->mapViolation($constraintViolation, $form, true);

        $this->assertCount(0, $form->getErrors());
        $this->assertCount(1, $form->get('name')->getErrors());
        $this->assertSame('This value should not be used.', $form->get('name')->getErrors()[0]->getMessage());
    }

    public function testFormValidationForEntityWithUniqueGroupFieldsNotValid()
    {
        $entity1 = new UniqueFieldFormValidationEntity(1, 'Foo');

        $form = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, ['data_class' => UniqueFieldFormValidationEntity::class])
            ->add('name', TextTypeTest::TESTED_TYPE)
            ->add('token', TextTypeTest::TESTED_TYPE)
            ->getForm();

        $constraintViolation = new ConstraintViolation(
            'This value should not be used.',
            'This value should not be used.',
            [
                '{{ value }}' => 'myTokenValue, myNameValue',
                '{{ token value }}' => '"myTokenValue"',
                '{{ name value }}' => '"myNameValue"',
            ],
            $form,
            'data.name, token',
            'myTokenValue, myNameValue',
            null,
            'code',
            new UniqueEntity(
                ['name', 'token']
            ),
            $entity1
        );

        $violationMapper = new ViolationMapper();
        $violationMapper->mapViolation($constraintViolation, $form, true);

        $this->assertCount(1, $form->getErrors());
        $this->assertCount(0, $form->get('name')->getErrors());
        $this->assertSame('This value should not be used.', $form->getErrors()[0]->getMessage());
    }
}
