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
 * @author Hugo Hamon <hugohamon@neuf.fr>
 */
class DbalSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    const SESSION_ID = 'TuMQ6Py9ni795qp84AaH';
    const SESSION_DATA = 'dXNlcm5hbWU9aGhhbW9u';  // "username=hhamon" when Base64 decoded
    const SESSION_MAX_LIFETIME = 30;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }
    }

    private function createConnection()
    {
        return $this->getMockBuilder('Doctrine\DBAL\Connection')->disableOriginalConstructor()->getMock();
    }

    private function createStatement()
    {
        return $this->getMock('Doctrine\DBAL\Driver\Statement');
    }

    private function createPlatform()
    {
        return $this->getMockForAbstractClass('Doctrine\DBAL\Platforms\AbstractPlatform');
    }

    public function testOpenAndCloseSession()
    {
        $handler = new DbalSessionHandler($this->createConnection());

        $this->assertTrue($handler->open('/tmp', self::SESSION_ID));
        $this->assertTrue($handler->close());
    }

    /** @expectedException \RuntimeException */
    public function testWriteSessionDataFails()
    {
        $platform = $this->createPlatform();
        $platform->expects($this->once())->method('getName')->willReturn('dbvendor');

        $connection = $this->createConnection();
        $connection->expects($this->any())->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects($this->once())->method('prepare')->willThrowException(new \Exception());

        $handler = new DbalSessionHandler($connection);

        $handler->write(self::SESSION_ID, 'username=hhamon');
    }

    public function testWriteSessionDataInPostgresSucceeds()
    {
        $platform = $this->createPlatform();
        $platform->expects($this->once())->method('getName')->willReturn('dbvendor');

        $connection = $this->createConnection();
        $connection->expects($this->exactly(2))->method('getDatabasePlatform')->willReturn($platform);

        $updateStatement = $this->createStatement();
        $insertStatement = $this->createStatement();

        $connection
            ->expects($this->at(2))
            ->method('prepare')
            ->with($this->equalTo('UPDATE sessions SET s_data = :data, s_time = :time WHERE s_id = :id'))
            ->will($this->returnValue($updateStatement));

        $connection
            ->expects($this->at(3))
            ->method('prepare')
            ->with($this->equalTo('INSERT INTO sessions (s_id, s_data, s_time) VALUES (:id, :data, :time)'))
            ->will($this->returnValue($insertStatement));

        $updateStatement->expects($this->exactly(2))->method('bindParam');
        $updateStatement->expects($this->once())->method('bindValue');
        $updateStatement->expects($this->once())->method('execute');
        $updateStatement->expects($this->once())->method('rowCount')->willReturn(0);

        $insertStatement
            ->expects($this->at(0))
            ->method('bindParam')
            ->with(
                $this->equalTo(':id'),
                $this->equalTo(self::SESSION_ID),
                $this->equalTo(\PDO::PARAM_STR)
            )
        ;

        $insertStatement
            ->expects($this->at(1))
            ->method('bindParam')
            ->with(
                $this->equalTo(':data'),
                $this->equalTo(self::SESSION_DATA),
                $this->equalTo(\PDO::PARAM_STR)
            )
        ;

        $insertStatement
            ->expects($this->once())
            ->method('bindValue')
            ->with(
                $this->equalTo(':time'),
                $this->greaterThanOrEqual(time()),
                $this->equalTo(\PDO::PARAM_INT)
            )
        ;

        $insertStatement->expects($this->once())->method('execute');

        $handler = new DbalSessionHandler($connection, 'sessions', 's_id', 's_data', 's_time');

        $this->assertTrue($handler->write(self::SESSION_ID, 'username=hhamon'));
    }

    public function testWriteSessionDataSucceeds()
    {
        $statement = $this->createStatement();
        $platform = $this->createPlatform();
        $platform->expects($this->once())->method('getName')->willReturn('dbvendor');

        $connection = $this->createConnection();
        $connection->expects($this->any())->method('getDatabasePlatform')->willReturn($platform);

        $connection
            ->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo('UPDATE sessions SET s_data = :data, s_time = :time WHERE s_id = :id'))
            ->willReturn($statement);

        $statement
            ->expects($this->at(0))
            ->method('bindParam')
            ->with(
                $this->equalTo(':id'),
                $this->equalTo(self::SESSION_ID),
                $this->equalTo(\PDO::PARAM_STR)
            )
        ;

        $statement
            ->expects($this->at(1))
            ->method('bindParam')
            ->with(
                $this->equalTo(':data'),
                $this->equalTo(self::SESSION_DATA),
                $this->equalTo(\PDO::PARAM_STR)
            )
        ;

        $statement
            ->expects($this->once())
            ->method('bindValue')
            ->with(
                $this->equalTo(':time'),
                $this->greaterThanOrEqual(time()),
                $this->equalTo(\PDO::PARAM_INT)
            )
        ;

        $statement->expects($this->once())->method('execute');
        $statement->expects($this->once())->method('rowCount')->willReturn(1);

        $handler = new DbalSessionHandler($connection, 'sessions', 's_id', 's_data', 's_time');

        $this->assertTrue($handler->write(self::SESSION_ID, 'username=hhamon'));
    }

    /** @dataProvider providePlatformMergeQuery */
    public function testWriteSessionDataByMergeSucceeds($driver, $mergeSql)
    {
        $statement = $this->createStatement();
        $platform = $this->createPlatform();
        $platform->expects($this->once())->method('getName')->willReturn($driver);

        $connection = $this->createConnection();
        $connection->expects($this->any())->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects($this->once())->method('prepare')->with($this->equalTo($mergeSql))->willReturn($statement);

        $statement
            ->expects($this->at(0))
            ->method('bindParam')
            ->with(
                $this->equalTo(':id'),
                $this->equalTo(self::SESSION_ID),
                $this->equalTo(\PDO::PARAM_STR)
            )
        ;

        $statement
            ->expects($this->at(1))
            ->method('bindParam')
            ->with(
                $this->equalTo(':data'),
                $this->equalTo(self::SESSION_DATA),
                $this->equalTo(\PDO::PARAM_STR)
            )
        ;

        $statement
            ->expects($this->once())
            ->method('bindValue')
            ->with(
                $this->equalTo(':time'),
                $this->greaterThanOrEqual(time()),
                $this->equalTo(\PDO::PARAM_INT)
            )
        ;

        $statement->expects($this->once())->method('execute');

        $handler = new DbalSessionHandler($connection, 'sessions', 's_id', 's_data', 's_time');

        $this->assertTrue($handler->write(self::SESSION_ID, 'username=hhamon'));
    }

    public function providePlatformMergeQuery()
    {
        return array(
            array(
                'mysql',
                'INSERT INTO sessions (s_id, s_data, s_time) VALUES (:id, :data, :time) ON DUPLICATE KEY UPDATE s_data = VALUES(s_data), s_time = VALUES(s_time)',
            ),
            array(
                'oracle',
                'MERGE INTO sessions USING DUAL ON (s_id = :id) WHEN NOT MATCHED THEN INSERT (s_id, s_data, s_time) VALUES (:id, :data, :time) WHEN MATCHED THEN UPDATE SET s_data = :data, s_time = :time',
            ),
            array(
                'sqlite',
                'INSERT OR REPLACE INTO sessions (s_id, s_data, s_time) VALUES (:id, :data, :time)',
            ),
        );
    }

    /** @expectedException \RuntimeException */
    public function testDestroySessionFails()
    {
        $connection = $this->createConnection();
        $connection->expects($this->once())->method('prepare')->willThrowException(new \Exception());
        $connection->expects($this->never())->method('bindParam');
        $connection->expects($this->never())->method('execute');

        $handler = new DbalSessionHandler($connection);
        $handler->destroy(self::SESSION_ID);
    }

    public function testDestroySessionSucceeds()
    {
        $statement = $this->createStatement();

        $connection = $this->createConnection();
        $connection
            ->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo('DELETE FROM sessions WHERE s_id = :id'))
            ->willReturn($statement);

        $statement
            ->expects($this->once())
            ->method('bindParam')
            ->with(
                $this->equalTo(':id'),
                $this->equalTo(self::SESSION_ID),
                $this->equalTo(\PDO::PARAM_STR)
            )
        ;

        $statement->expects($this->once())->method('execute');

        $handler = new DbalSessionHandler($connection, 'sessions', 's_id', 's_data', 's_time');

        $this->assertTrue($handler->destroy(self::SESSION_ID));
    }

    public function testGarbageCollectionSucceeds()
    {
        $statement = $this->createStatement();

        $connection = $this->createConnection();

        $connection
            ->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo('DELETE FROM sessions WHERE s_time < :time'))
            ->willReturn($statement);

        $statement
            ->expects($this->once())
            ->method('bindValue')
            ->with(
                $this->equalTo(':time'),
                $this->lessThanOrEqual(time() - self::SESSION_MAX_LIFETIME),
                $this->equalTo(\PDO::PARAM_INT)
            )
        ;

        $statement->expects($this->once())->method('execute');

        $handler = new DbalSessionHandler($connection, 'sessions', 's_id', 's_data', 's_time');

        $this->assertTrue($handler->gc(self::SESSION_MAX_LIFETIME));
    }

    /** @expectedException \RuntimeException */
    public function testGarbageCollectionFails()
    {
        $connection = $this->createConnection();
        $connection->expects($this->once())->method('prepare')->willThrowException(new \Exception());
        $connection->expects($this->never())->method('bindValue');
        $connection->expects($this->never())->method('execute');

        $handler = new DbalSessionHandler($connection);
        $handler->gc(self::SESSION_MAX_LIFETIME);
    }

    /** @expectedException \RuntimeException */
    public function testReadSessionDataFails()
    {
        $connection = $this->createConnection();
        $connection->expects($this->once())->method('prepare')->willThrowException(new \Exception());
        $connection->expects($this->never())->method('bindParam');
        $connection->expects($this->never())->method('execute');
        $connection->expects($this->never())->method('fetchAll');

        $handler = new DbalSessionHandler($connection);
        $handler->read(self::SESSION_ID);
    }

    /** @dataProvider provideSessionData */
    public function testReadSessionSucceeds($dbSessData, $decodedSessData)
    {
        $statement = $this->createStatement();
        $statement->expects($this->once())->method('bindParam')->with(
            $this->equalTo(':id'),
            $this->equalTo(self::SESSION_ID),
            $this->equalTo(\PDO::PARAM_STR)
        );
        $statement->expects($this->once())->method('execute');
        $statement
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->equalTo(\PDO::FETCH_NUM))
            ->willReturn($dbSessData);

        $connection = $this->createConnection();
        $connection
            ->expects($this->once())
            ->method('prepare')
            ->with($this->equalTo('SELECT s_data FROM sessions WHERE s_id = :id'))
            ->willReturn($statement);

        $handler = new DbalSessionHandler($connection, 'sessions', 's_id', 's_data', 's_time');

        $this->assertSame($decodedSessData, $handler->read(self::SESSION_ID));
    }

    public function provideSessionData()
    {
        return array(
            array(
                false,
                '',
            ),
            array(
                array(array(self::SESSION_DATA)),
                'username=hhamon',
            ),
        );
    }
}
