<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Security\User;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Bridge\Doctrine\Tests\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\User;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class EntityUserProviderTest extends TestCase
{
    public function testRefreshUserGetsUserByPrimaryKey()
    {
        $em = DoctrineTestHelper::createTestEntityManager();
        $this->createSchema($em);

        $user1 = new User(1, 1, 'user1');
        $user2 = new User(1, 2, 'user2');

        $em->persist($user1);
        $em->persist($user2);
        $em->flush();

        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User', 'name');

        // try to change the user identity
        $user1->name = 'user2';

        $this->assertSame($user1, $provider->refreshUser($user1));
    }

    public function testLoadUserByUsername()
    {
        $em = DoctrineTestHelper::createTestEntityManager();
        $this->createSchema($em);

        $user = new User(1, 1, 'user1');

        $em->persist($user);
        $em->flush();

        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User', 'name');

        $this->assertSame($user, $provider->loadUserByIdentifier('user1'));
    }

    public function testLoadUserByUsernameWithUserLoaderRepositoryAndWithoutProperty()
    {
        $user = new User(1, 1, 'user1');

        $repository = $this->createMock(UserLoaderRepository::class);
        $repository
            ->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with('user1')
            ->willReturn($user);

        $em = $this->createMock(EntityManager::class);
        $em
            ->expects($this->once())
            ->method('getRepository')
            ->with('Symfony\Bridge\Doctrine\Tests\Fixtures\User')
            ->willReturn($repository);

        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User');
        $this->assertSame($user, $provider->loadUserByIdentifier('user1'));
    }

    public function testLoadUserByUsernameWithNonUserLoaderRepositoryAndWithoutProperty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You must either make the "Symfony\Bridge\Doctrine\Tests\Fixtures\User" entity Doctrine Repository ("Doctrine\ORM\EntityRepository") implement "Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface" or set the "property" option in the corresponding entity provider configuration.');
        $em = DoctrineTestHelper::createTestEntityManager();
        $this->createSchema($em);

        $user = new User(1, 1, 'user1');

        $em->persist($user);
        $em->flush();

        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User');
        $provider->loadUserByIdentifier('user1');
    }

    public function testRefreshUserRequiresId()
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $user1 = new User(null, null, 'user1');
        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User', 'name');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot refresh a user from the EntityUserProvider that does not contain an identifier. The user object has to be serialized with its own identifier mapped by Doctrine');
        $provider->refreshUser($user1);
    }

    public function testRefreshInvalidUser()
    {
        $em = DoctrineTestHelper::createTestEntityManager();
        $this->createSchema($em);

        $user1 = new User(1, 1, 'user1');

        $em->persist($user1);
        $em->flush();

        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User', 'name');

        $user2 = new User(1, 2, 'user2');
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User with id {"id1":1,"id2":2} not found');

        $provider->refreshUser($user2);
    }

    public function testSupportProxy()
    {
        $em = DoctrineTestHelper::createTestEntityManager();
        $this->createSchema($em);

        $user1 = new User(1, 1, 'user1');

        $em->persist($user1);
        $em->flush();
        $em->clear();

        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User', 'name');

        $user2 = $em->getReference('Symfony\Bridge\Doctrine\Tests\Fixtures\User', ['id1' => 1, 'id2' => 1]);
        $this->assertTrue($provider->supportsClass($user2::class));
    }

    public function testLoadUserByUserNameShouldLoadUserWhenProperInterfaceProvided()
    {
        $repository = $this->createMock(UserLoaderRepository::class);
        $repository->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with('name')
            ->willReturn(
                $this->createMock(UserInterface::class)
            );

        $provider = new EntityUserProvider(
            $this->getManager($this->getObjectManager($repository)),
            'Symfony\Bridge\Doctrine\Tests\Fixtures\User'
        );

        $provider->loadUserByIdentifier('name');
    }

    public function testLoadUserByUserNameShouldDeclineInvalidInterface()
    {
        $this->expectException(\InvalidArgumentException::class);
        $repository = $this->createMock(ObjectRepository::class);

        $provider = new EntityUserProvider(
            $this->getManager($this->getObjectManager($repository)),
            'Symfony\Bridge\Doctrine\Tests\Fixtures\User'
        );

        $provider->loadUserByIdentifier('name');
    }

    public function testPasswordUpgrades()
    {
        $user = new User(1, 1, 'user1');

        $repository = $this->createMock(PasswordUpgraderRepository::class);
        $repository->expects($this->once())
            ->method('upgradePassword')
            ->with($user, 'foobar');

        $provider = new EntityUserProvider(
            $this->getManager($this->getObjectManager($repository)),
            'Symfony\Bridge\Doctrine\Tests\Fixtures\User'
        );

        $provider->upgradePassword($user, 'foobar');
    }

    private function getManager($em, $name = null)
    {
        $manager = $this->createMock(ManagerRegistry::class);
        $manager->expects($this->any())
            ->method('getManager')
            ->with($this->equalTo($name))
            ->willReturn($em);

        return $manager;
    }

    private function getObjectManager($repository)
    {
        $em = $this->getMockBuilder(ObjectManager::class)
            ->onlyMethods(['getClassMetadata', 'getRepository'])
            ->getMockForAbstractClass();
        $em->expects($this->any())
            ->method('getRepository')
            ->willReturn($repository);

        return $em;
    }

    private function createSchema($em)
    {
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema([
            $em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\User'),
        ]);
    }
}

abstract class UserLoaderRepository implements ObjectRepository, UserLoaderInterface
{
    abstract public function loadUserByIdentifier(string $identifier): ?UserInterface;
}

abstract class PasswordUpgraderRepository implements ObjectRepository, PasswordUpgraderInterface
{
    abstract public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void;
}
