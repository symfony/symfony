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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;

class UniqueEntityTest extends TestCase
{
    public function testAttributeWithDefaultProperty()
    {
        $metadata = new ClassMetadata(UniqueEntityDummyOne::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        /** @var UniqueEntity $constraint */
        [$constraint] = $metadata->getConstraints();
        self::assertSame(['email'], $constraint->fields);
        self::assertTrue($constraint->ignoreNull);
        self::assertSame('doctrine.orm.validator.unique', $constraint->validatedBy());
        self::assertSame(['Default', 'UniqueEntityDummyOne'], $constraint->groups);
    }

    public function testAttributeWithCustomizedService()
    {
        $metadata = new ClassMetadata(UniqueEntityDummyTwo::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        /** @var UniqueEntity $constraint */
        [$constraint] = $metadata->getConstraints();
        self::assertSame(['isbn'], $constraint->fields);
        self::assertSame('my_own_validator', $constraint->validatedBy());
        self::assertSame('my_own_entity_manager', $constraint->em);
        self::assertSame('App\Entity\MyEntity', $constraint->entityClass);
        self::assertSame('fetchDifferently', $constraint->repositoryMethod);
    }

    public function testAttributeWithGroupsAndPaylod()
    {
        $metadata = new ClassMetadata(UniqueEntityDummyThree::class);
        $loader = new AnnotationLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        /** @var UniqueEntity $constraint */
        [$constraint] = $metadata->getConstraints();
        self::assertSame('uuid', $constraint->fields);
        self::assertSame('id', $constraint->errorPath);
        self::assertSame('some attached data', $constraint->payload);
        self::assertSame(['some_group'], $constraint->groups);
    }
}

#[UniqueEntity(['email'], message: 'myMessage')]
class UniqueEntityDummyOne
{
    private $email;
}

#[UniqueEntity(fields: ['isbn'], service: 'my_own_validator', em: 'my_own_entity_manager', entityClass: 'App\Entity\MyEntity', repositoryMethod: 'fetchDifferently')]
class UniqueEntityDummyTwo
{
    private $isbn;
}

#[UniqueEntity('uuid', ignoreNull: false, errorPath: 'id', payload: 'some attached data', groups: ['some_group'])]
class UniqueEntityDummyThree
{
    private $id;
    private $uuid;
}
