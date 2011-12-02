<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\Security\User;

require_once __DIR__.'/../../DoctrineOrmTestCase.php';
require_once __DIR__.'/../../Fixtures/CompositeIdentEntity.php';

use Symfony\Tests\Bridge\Doctrine\DoctrineOrmTestCase;
use Symfony\Tests\Bridge\Doctrine\Fixtures\CompositeIdentEntity;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Doctrine\ORM\Tools\SchemaTool;

class EntityUserProviderTest extends DoctrineOrmTestCase
{
    public function testRefreshUserGetsUserByPrimaryKey()
    {
        $em = $this->createTestEntityManager();
        $this->createSchema($em);

        $user1 = new CompositeIdentEntity(1, 1, 'user1');
        $user2 = new CompositeIdentEntity(1, 2, 'user2');

        $em->persist($user1);
        $em->persist($user2);
        $em->flush();

        $provider = new EntityUserProvider($em, 'Symfony\Tests\Bridge\Doctrine\Fixtures\CompositeIdentEntity', 'name');

        // try to change the user identity
        $user1->name = 'user2';

        $this->assertSame($user1, $provider->refreshUser($user1));
    }
    
    public function testRefreshUserRequiresId()
    {
        $em = $this->createTestEntityManager();
        
        $user1 = new CompositeIdentEntity(null, null, 'user1');
        $provider = new EntityUserProvider($em, 'Symfony\Tests\Bridge\Doctrine\Fixtures\CompositeIdentEntity', 'name');
        
        $this->setExpectedException(
            'InvalidArgumentException',
            'You cannot refresh a user from the EntityUserProvider that does not contain an identifier. The user object has to be serialized with its own identifier mapped by Doctrine'
        );
        $provider->refreshUser($user1);
    }

    private function createSchema($em)
    {
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema(array(
            $em->getClassMetadata('Symfony\Tests\Bridge\Doctrine\Fixtures\CompositeIdentEntity'),
        ));
    }
}
