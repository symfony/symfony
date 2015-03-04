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

trigger_error('The '.__NAMESPACE__.'\DoctrineOrmTestCase class is deprecated since version 2.4 and will be removed in 3.0. Use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper class instead.', E_USER_DEPRECATED);

use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;

/**
 * Class DoctrineOrmTestCase.
 *
 * @deprecated since version 2.4, to be removed in 3.0.
 *             Use {@link DoctrineTestHelper} instead.
 */
abstract class DoctrineOrmTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @return EntityManager
     */
    public static function createTestEntityManager()
    {
        return DoctrineTestHelper::createTestEntityManager();
    }
}
