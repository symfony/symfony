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

use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\User;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Doctrine\ORM\Tools\SchemaTool;

class EntityUserProviderTest extends \PHPUnit_Framework_TestCase
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

    public function testRefreshUserRequiresId()
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $user1 = new User(null, null, 'user1');
        $provider = new EntityUserProvider($this->getManager($em), 'Symfony\Bridge\Doctrine\Tests\Fixtures\User', 'name');

        $this->setExpectedException(
            'InvalidArgumentException',
            'You cannot refresh a user from the EntityUserProvider that does not contain an identifier. The user object has to be serialized with its own identifier mapped by Doctrine'
        );
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
        $this->setExpectedException(
            'Symfony\Component\Security\Core\Exception\UsernameNotFoundException',
            'User with id {"id1":1,"id2":2} not found'
        );
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

        $user2 = $em->getReference('Symfony\Bridge\Doctrine\Tests\Fixtures\User', array('id1' => 1, 'id2' => 1));
        $this->assertTrue($provider->supportsClass(get_class($user2)));
    }

    public function testLoadUserByUserNameShouldLoadUserWhenProperInterfaceProvided()
    {
        $repository = $this->getMock('\Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface');
        $repository->expects($this->once())
            ->method('loadUserByUsername')
            ->with('name')
            ->willReturn(
                $this->getMock('\Symfony\Component\Security\Core\User\UserInterface')
            );

        $provider = new EntityUserProvider(
            $this->getManager($this->getObjectManager($repository)),
            'Symfony\Bridge\Doctrine\Tests\Fixtures\User'
        );

        $provider->loadUserByUsername('name');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLoadUserByUserNameShouldDeclineInvalidInterface()
    {
        $repository = $this->getMock('\Symfony\Component\Security\Core\User\AdvancedUserInterface');

        $provider = new EntityUserProvider(
            $this->getManager($this->getObjectManager($repository)),
            'Symfony\Bridge\Doctrine\Tests\Fixtures\User'
        );

        $provider->loadUserByUsername('name');
    }

    private function getManager($em, $name = null)
    {
        $manager = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $manager->expects($this->once())
            ->method('getManager')
            ->with($this->equalTo($name))
            ->will($this->returnValue($em));

        return $manager;
    }

    private function getObjectManager($repository)
    {
        $em = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
            ->setMethods(array('getClassMetadata', 'getRepository'))
            ->getMockForAbstractClass();
        $em->expects($this->any())
            ->method('getRepository')
            ->willReturn($repository);

        return $em;
    }

    private function createSchema($em)
    {
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema(array(
            $em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\User'),
        ));
    }
}
