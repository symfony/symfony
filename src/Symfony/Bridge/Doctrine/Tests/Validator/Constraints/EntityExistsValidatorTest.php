<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Validator\Constraints;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Doctrine\Tests\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\UserUuidNameEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\UuidIdEntity;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\EntityExists;
use Symfony\Bridge\Doctrine\Validator\Constraints\EntityExistsValidator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class EntityExistsValidatorTest extends ConstraintValidatorTestCase
{
    protected ?ObjectManager $em = null;
    protected ManagerRegistry $registry;

    protected function setUp(): void
    {
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }

        $this->em = DoctrineTestHelper::createTestEntityManager();
        $this->createSchema($this->em);
        $this->registry = $this->createRegistryMock($this->em);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->em?->close();
        $this->em = null;

        parent::tearDown();
    }

    protected function createValidator(): EntityExistsValidator
    {
        return new EntityExistsValidator($this->registry);
    }

    public function testValidateSkipsNull()
    {
        $constraint = new EntityExists(entityClass: SingleIntIdEntity::class);

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateSkipsEmptyString()
    {
        $this->validator->validate('', new EntityExists(entityClass: SingleIntIdEntity::class));

        $this->assertNoViolation();
    }

    public function testValidateExistingEntityBySingleIdentifier()
    {
        $this->em->persist(new SingleIntIdEntity(1, 'Foo'));
        $this->em->flush();

        $this->validator->validate(1, new EntityExists(entityClass: SingleIntIdEntity::class, message: 'myMessage'));

        $this->assertNoViolation();
    }

    public function testValidateExistingEntityByUuidIdentifier()
    {
        $id = Uuid::fromString('ec562e21-1fc8-4e55-8de7-a42389ac75c5');
        $this->em->persist(new UserUuidNameEntity($id, 'Foo Bar'));
        $this->em->flush();

        $this->validator->validate((string) $id, new EntityExists(entityClass: UserUuidNameEntity::class, message: 'myMessage'));

        $this->assertNoViolation();
    }

    public function testRaiseViolationWhenEntityDoesNotExist()
    {
        $this->validator->validate(999, new EntityExists(entityClass: SingleIntIdEntity::class, message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '999')
            ->setInvalidValue(999)
            ->setCode(EntityExists::ENTITY_NOT_FOUND_ERROR)
            ->assertRaised();
    }

    #[DataProvider('provideInvalidUuidValues')]
    public function testRaiseInvalidValueViolationWhenValueCannotBeConvertedToTheFieldType(string $value)
    {
        $this->validator->validate($value, new EntityExists(entityClass: UuidIdEntity::class, invalidMessage: 'myInvalidMessage'));

        $this->buildViolation('myInvalidMessage')
            ->setParameter('{{ value }}', \sprintf('"%s"', $value))
            ->setInvalidValue($value)
            ->setCode(EntityExists::INVALID_VALUE_ERROR)
            ->assertRaised();
    }

    public function testASixteenByteStringIsAValidBinaryUuid()
    {
        // 16 byte strings are valid binary uuid input, so this is a not-found, not an invalid value
        $this->validator->validate('not-a-valid-uuid', new EntityExists(entityClass: UuidIdEntity::class, message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"not-a-valid-uuid"')
            ->setInvalidValue('not-a-valid-uuid')
            ->setCode(EntityExists::ENTITY_NOT_FOUND_ERROR)
            ->assertRaised();
    }

    public function testValidateExistingEntityByRealUuidType()
    {
        $id = Uuid::fromString('ec562e21-1fc8-4e55-8de7-a42389ac75c5');
        $this->em->persist(new UuidIdEntity($id));
        $this->em->flush();

        $this->validator->validate((string) $id, new EntityExists(entityClass: UuidIdEntity::class));

        $this->assertNoViolation();
    }

    public function testValidateExistingEntityByAssociation()
    {
        $single = new SingleIntIdEntity(1, 'Foo');
        $association = new AssociationEntity();
        $association->single = $single;
        $this->em->persist($single);
        $this->em->persist($association);
        $this->em->flush();

        $this->validator->validate(1, new EntityExists(entityClass: AssociationEntity::class, identifierField: 'single'));

        $this->assertNoViolation();
    }

    public function testRaiseViolationWhenNoEntityReferencesTheAssociation()
    {
        $this->validator->validate(999, new EntityExists(entityClass: AssociationEntity::class, identifierField: 'single', message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '999')
            ->setInvalidValue(999)
            ->setCode(EntityExists::ENTITY_NOT_FOUND_ERROR)
            ->assertRaised();
    }

    #[DataProvider('provideNegativeIntegerValues')]
    public function testRaiseViolationWhenIntegerIdentifierIsNegative(mixed $value, string $formatted)
    {
        $this->validator->validate($value, new EntityExists(entityClass: SingleIntIdEntity::class, message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formatted)
            ->setInvalidValue($value)
            ->setCode(EntityExists::ENTITY_NOT_FOUND_ERROR)
            ->assertRaised();
    }

    public function testValidateUsingRepositoryMethodReturningObject()
    {
        /** @var EntityRepository $repository */
        $repository = $this->em->getRepository(SingleIntIdEntity::class);
        $repository->result = new \stdClass();

        $this->validator->validate('ignored', new EntityExists(
            entityClass: SingleIntIdEntity::class,
            repositoryMethod: 'findByCustom',
        ));

        $this->assertNoViolation();
    }

    public function testRaiseViolationWhenRepositoryMethodReturnsNull()
    {
        /** @var EntityRepository $repository */
        $repository = $this->em->getRepository(SingleIntIdEntity::class);
        $repository->result = null;

        $this->validator->validate('unknown', new EntityExists(
            entityClass: SingleIntIdEntity::class,
            message: 'myMessage',
            repositoryMethod: 'findByCustom',
        ));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"unknown"')
            ->setInvalidValue('unknown')
            ->setCode(EntityExists::ENTITY_NOT_FOUND_ERROR)
            ->assertRaised();
    }

    public function testThrowsWhenEntityHasMultipleIdentifiersAndIdentifierFieldIsNotConfigured()
    {
        $constraint = new EntityExists(entityClass: CompositeIntIdEntity::class);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('Entity "Symfony\\Bridge\\Doctrine\\Tests\\Fixtures\\CompositeIntIdEntity" has multiple identifier fields. Use the "identifierField" option to specify which one to use.');

        $this->validator->validate(1, $constraint);
    }

    public function testThrowsWhenIdentifierFieldDoesNotExist()
    {
        $constraint = new EntityExists(entityClass: SingleIntIdEntity::class, identifierField: 'nonExistentField');

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('Entity "Symfony\\Bridge\\Doctrine\\Tests\\Fixtures\\SingleIntIdEntity" does not have a field named "nonExistentField".');

        $this->validator->validate('Foo', $constraint);
    }

    public function testValidateExistingEntityByRegularIdentifierField()
    {
        $this->em->persist(new SingleIntIdEntity(1, 'Foo'));
        $this->em->flush();

        $this->validator->validate('Foo', new EntityExists(entityClass: SingleIntIdEntity::class, identifierField: 'name'));

        $this->assertNoViolation();
    }

    public function testThrowsWhenRepositoryMethodDoesNotExist()
    {
        $constraint = new EntityExists(entityClass: SingleIntIdEntity::class, repositoryMethod: 'nonExistentMethod');

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('Repository for class "Symfony\\Bridge\\Doctrine\\Tests\\Fixtures\\SingleIntIdEntity" does not have method "nonExistentMethod".');

        $this->validator->validate('ignored', $constraint);
    }

    public function testThrowsWhenObjectManagerDoesNotExist()
    {
        $this->registry = $this->createRegistryMock($this->em, true, true);
        $this->validator = $this->createValidator();

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('Object manager "missing" does not exist.');

        $this->validator->validate(1, new EntityExists(entityClass: SingleIntIdEntity::class, em: 'missing'));
    }

    public function testThrowsWhenManagerForClassCannotBeResolved()
    {
        $this->registry = $this->createRegistryMock($this->em, false);
        $this->validator = $this->createValidator();

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('Unable to find the object manager associated with an entity of class "Symfony\\Bridge\\Doctrine\\Tests\\Fixtures\\SingleIntIdEntity".');

        $this->validator->validate(1, new EntityExists(entityClass: SingleIntIdEntity::class));
    }

    private function createRegistryMock(?ObjectManager $em, bool $managerForClassExists = true, bool $throwOnGetManager = false): ManagerRegistry
    {
        $registry = $this->createStub(ManagerRegistry::class);

        if ($throwOnGetManager) {
            $registry->method('getManager')
                ->willThrowException(new \InvalidArgumentException());
        } else {
            $registry->method('getManager')
                ->willReturn($em);
        }

        $registry->method('getManagerForClass')
            ->willReturn($managerForClassExists ? $em : null);

        $registry->method('getConnection')
            ->willReturn($em?->getConnection());

        return $registry;
    }

    public static function provideInvalidUuidValues(): iterable
    {
        yield ['abc'];
        yield ['random-string'];
        yield ['123'];
    }

    public static function provideNegativeIntegerValues(): iterable
    {
        yield 'negative int' => [-1, '-1'];
        yield 'another negative int' => [-42, '-42'];
        yield 'negative numeric string' => ['-10', '"-10"'];
    }

    /**
     * @param EntityManagerInterface $em
     */
    private function createSchema(ObjectManager $em): void
    {
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema([
            $em->getClassMetadata(SingleIntIdEntity::class),
            $em->getClassMetadata(CompositeIntIdEntity::class),
            $em->getClassMetadata(UserUuidNameEntity::class),
            $em->getClassMetadata(UuidIdEntity::class),
            $em->getClassMetadata(AssociationEntity::class),
        ]);
    }
}
