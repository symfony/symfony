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
    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public static function createTestEntityManager()
    {
        return DoctrineTestHelper::createTestEntityManager();
    }
}
