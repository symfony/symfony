<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests;

use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;

/**
 * Class DoctrineOrmTestCase
 *
 * @deprecated Deprecated as of Symfony 2.4, to be removed in Symfony 3.0.
 *             Use {@link DoctrineTestHelper} instead.
 */
abstract class DoctrineOrmTestCase extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Doctrine\Common\Version')) {
            $this->markTestSkipped('Doctrine Common is not available.');
        }

        if (!class_exists('Doctrine\DBAL\Platforms\MySqlPlatform')) {
            $this->markTestSkipped('Doctrine DBAL is not available.');
        }

        if (!class_exists('Doctrine\ORM\EntityManager')) {
            $this->markTestSkipped('Doctrine ORM is not available.');
        }
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public static function createTestEntityManager()
    {
        return DoctrineTestHelper::createTestEntityManager();
    }
}
