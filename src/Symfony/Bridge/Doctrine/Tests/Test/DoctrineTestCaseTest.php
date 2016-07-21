<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Test;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Doctrine\Test\DoctrineTestCase;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\User;

class DoctrineTestCaseTest extends DoctrineTestCase
{
    private static $em;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$em = DoctrineTestHelper::createTestEntityManager();
        self::createSchema();
    }

    public function setUp()
    {
        $this->setEntityManager(self::$em);

        parent::setUp();
    }

    public function testItInsertsData()
    {
        $user = new User(1, 1, 'user');

        self::$em->persist($user);
        self::$em->flush();

        $this->assertEquals(1, count(self::$em->getRepository(User::class)->findAll()));
    }

    public function testItHasRollbacked()
    {
        $user = new User(1, 2, 'user');

        self::$em->persist($user);
        self::$em->flush();

        $this->assertEquals(1, count(self::$em->getRepository(User::class)->findAll()));
    }

    private static function createSchema()
    {
        $schemaTool = new SchemaTool(self::$em);
        $schemaTool->createSchema(array(
            self::$em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\User'),
        ));
    }
}
