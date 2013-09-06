<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\HttpFoundation;

use Symfony\Bridge\Doctrine\HttpFoundation\DbalSessionHandler;

/**
 * Test class for DbalSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 */
class DbalSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $this->connection = $this->getMock('Doctrine\DBAL\Driver\Connection');
        $mock = $this->getMockBuilder('Symfony\Bridge\Doctrine\HttpFoundation\DbalSessionHandler');
        $mock->setConstructorArgs(array($this->connection));
        $this->driver = $mock->getMock();
    }
}
