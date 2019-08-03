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

use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\User;

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

        $this->assertSame($user, $provider->loadUserByUsername('user1'));
    }

    public function testLoadUserByUsernameWithUserLoaderRepositoryAndWithoutProperty()
    {
        $user = new User(1, 1, 'user1');

        $repository = $this->getMockBuilder('Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $repository
            ->expects($this->once())
            ->method('loadUserByUsername')
            ->with('user1')
            ->willReturn($user);

        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $em
            ->expects($this->once())
            ->method('getRepository')
            ->with('Symfony\Bridge\Doctrine\Tests\Fixtures\User')
            ->willReturn($repository);

        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User');
        $this->assertSame($user, $provider->loadUserByUsername('user1'));
    }

    public function testLoadUserByUsernameWithNonUserLoaderRepositoryAndWithoutProperty()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('You must either make the "Symfony\Bridge\Doctrine\Tests\Fixtures\User" entity Doctrine Repository ("Doctrine\ORM\EntityRepository") implement "Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface" or set the "property" option in the corresponding entity provider configuration.');
        $em = DoctrineTestHelper::createTestEntityManager();
        $this->createSchema($em);

        $user = new User(1, 1, 'user1');

        $em->persist($user);
        $em->flush();

        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User');
        $provider->loadUserByUsername('user1');
    }

    public function testRefreshUserRequiresId()
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $user1 = new User(null, null, 'user1');
        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User', 'name');

        $this->expectException('InvalidArgumentException');
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
        $this->expectException('Symfony\Component\Security\Core\Exception\UsernameNotFoundException');
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
        $this->assertTrue($provider->supportsClass(\get_class($user2)));
    }

    public function testLoadUserByUserNameShouldLoadUserWhenProperInterfaceProvided()
    {
        $repository = $this->getMockBuilder('\Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface')->getMock();
        $repository->expects($this->once())
            ->method('loadUserByUsername')
            ->with('name')
            ->willReturn(
                $this->getMockBuilder('\Symfony\Component\Security\Core\User\UserInterface')->getMock()
            );

        $provider = new EntityUserProvider(
            $this->getManager($this->getObjectManager($repository)),
            'Symfony\Bridge\Doctrine\Tests\Fixtures\User'
        );

        $provider->loadUserByUsername('name');
    }

    public function testLoadUserByUserNameShouldDeclineInvalidInterface()
    {
        $this->expectException('InvalidArgumentException');
        $repository = $this->getMockBuilder('\Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();

        $provider = new EntityUserProvider(
            $this->getManager($this->getObjectManager($repository)),
            'Symfony\Bridge\Doctrine\Tests\Fixtures\User'
        );

        $provider->loadUserByUsername('name');
    }

    private function getManager($em, $name = null)
    {
        $manager = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();
        $manager->expects($this->any())
            ->method('getManager')
            ->with($this->equalTo($name))
            ->willReturn($em);

        return $manager;
    }

    private function getObjectManager($repository)
    {
        $em = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
            ->setMethods(['getClassMetadata', 'getRepository'])
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
