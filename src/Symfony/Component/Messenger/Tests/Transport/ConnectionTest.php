<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

class ConnectionTest extends TestCase
{
    public function testTableNotFoundExceptionWithEnabledAutoSetup()
    {
        if (!class_exists(DBALConnection::class)) {
            $this->markTestSkipped('Unable to run this test without Doctrine');
        }

        $dbalConnection = $this->getDbalConnectionMock(TableNotFoundException::class);
        $transportConnection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([['auto_setup' => true], $dbalConnection])
            ->onlyMethods(['setup'])
            ->getMock();

        $transportConnection->expects($this->once())
            ->method('setup');
        $this->expectExceptionMessage('Transaction started');

        $transportConnection->get();
    }

    public function testTableNotFoundExceptionWithDisabledAutoSetup()
    {
        if (!class_exists(DBALConnection::class)) {
            $this->markTestSkipped('Unable to run this test without Doctrine');
        }

        $dbalConnection = $this->getDbalConnectionMock(TableNotFoundException::class);
        $transportConnection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([['auto_setup' => false], $dbalConnection])
            ->onlyMethods(['setup'])
            ->getMock();

        $transportConnection->expects($this->never())
            ->method('setup');
        $this->expectExceptionMessage('Transaction started');

        $transportConnection->get();
    }

    public function testDriverException()
    {
        if (!class_exists(DBALConnection::class)) {
            $this->markTestSkipped('Unable to run this test without Doctrine');
        }

        $dbalConnection = $this->getDbalConnectionMock(DriverException::class);
        $transportConnection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([['auto_setup' => false], $dbalConnection])
            ->onlyMethods(['setup'])
            ->getMock();

        $transportConnection->expects($this->never())
            ->method('setup');
        $this->expectExceptionMessage('Transaction started');

        $transportConnection->get();
    }

    private function getDbalConnectionMock(string $exceptionClass): MockObject
    {
        $dbalConnection = $this->createMock(DBALConnection::class);

        $exception = $this->createMock($exceptionClass);
        $dbalConnection->method('delete')->willThrowException($exception);

        $dbalConnection->method('beginTransaction')->willThrowException(new Exception('Transaction started'));

        $platform = $this->createMock(MySQLPlatform::class);
        $dbalConnection->method('getDatabasePlatform')->willReturn($platform);

        return $dbalConnection;
    }
}
