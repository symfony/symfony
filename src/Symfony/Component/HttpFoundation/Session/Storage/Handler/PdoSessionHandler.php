<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage\Handler;

/**
 * Session handler using a PDO connection to read and write data.
 *
 * It works with MySQL, PostgreSQL, Oracle, SQL Server and SQLite and implements
 * locking of sessions to prevent loss of data by concurrent access to the same session.
 * This means requests for the same session will wait until the other one finished.
 * PHPs internal files session handler also works this way.
 *
 * Attention: Since SQLite does not support row level locks but locks the whole database,
 * it means only one session can be accessed at a time. Even different sessions would wait
 * for another to finish. So saving session in SQLite should only be considered for
 * development or prototypes.
 *
 * Session data is a binary string that can contain non-printable characters like the null byte.
 * For this reason it must be saved in a binary column in the database like BLOB in MySQL.
 * Saving it in a character column could corrupt the data. You can use createTable()
 * to initialize a correctly defined table.
 *
 * @see http://php.net/sessionhandlerinterface
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Michael Williams <michael.williams@funsational.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class PdoSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var \PDO|null PDO instance or null when not connected yet
     */
    private $pdo;

    /**
     * @var string|null|false DSN string or null for session.save_path or false when lazy connection disabled
     */
    private $dsn = false;

    /**
     * @var string Database driver
     */
    private $driver;

    /**
     * @var string Table name
     */
    private $table;

    /**
     * @var string Column for session id
     */
    private $idCol;

    /**
     * @var string Column for session data
     */
    private $dataCol;

    /**
     * @var string Column for lifetime
     */
    private $lifetimeCol;

    /**
     * @var string Column for timestamp
     */
    private $timeCol;

    /**
     * @var string Username when lazy-connect
     */
    private $username;

    /**
     * @var string Password when lazy-connect
     */
    private $password;

    /**
     * @var array Connection options when lazy-connect
     */
    private $connectionOptions = array();

    /**
     * @var bool Whether a transaction is active
     */
    private $inTransaction = false;

    /**
     * @var bool Whether gc() has been called
     */
    private $gcCalled = false;

    /**
     * Constructor.
     *
     * You can either pass an existing database connection as PDO instance or
     * pass a DSN string that will be used to lazy-connect to the database
     * when the session is actually used. Furthermore it's possible to pass null
     * which will then use the session.save_path ini setting as PDO DSN parameter.
     * Since locking uses a transaction between opening and closing a session,
     * it's not recommended to use the same database connection that you also use
     * for your application logic.
     *
     * List of available options:
     *  * db_table: The name of the table [default: sessions]
     *  * db_id_col: The column where to store the session id [default: sess_id]
     *  * db_data_col: The column where to store the session data [default: sess_data]
     *  * db_lifetime_col: The column where to store the lifetime [default: sess_lifetime]
     *  * db_time_col: The column where to store the timestamp [default: sess_time]
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: array()]
     *
     * @param \PDO|string|null $pdoOrDsn A \PDO instance or DSN string or null
     * @param array            $options  An associative array of DB options
     *
     * @throws \InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     */
    public function __construct($pdoOrDsn, array $options = array())
    {
        if ($pdoOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $pdoOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new \InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION))', __CLASS__));
            }

            $this->pdo = $pdoOrDsn;
            $this->driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } else {
            $this->dsn = $pdoOrDsn;
        }

        $options = array_replace(array(
            'db_table'              => 'sessions',
            'db_id_col'             => 'sess_id',
            'db_data_col'           => 'sess_data',
            'db_lifetime_col'       => 'sess_lifetime',
            'db_time_col'           => 'sess_time',
            'db_username'           => '',
            'db_password'           => '',
            'db_connection_options' => array()
        ), $options);

        $this->table = $options['db_table'];
        $this->idCol = $options['db_id_col'];
        $this->dataCol = $options['db_data_col'];
        $this->lifetimeCol = $options['db_lifetime_col'];
        $this->timeCol = $options['db_time_col'];
        $this->username = $options['db_username'];
        $this->password = $options['db_password'];
        $this->connectionOptions = $options['db_connection_options'];
    }

    /**
     * Creates the table to store sessions which can be called once for setup.
     *
     * Session ID is saved in a VARCHAR(128) column because that is enough even for
     * a 512 bit configured session.hash_function like Whirlpool. Session data is
     * saved in a BLOB. One could also use a shorter inlined varbinary column
     * if one was sure the data fits into it.
     *
     * @throws \PDOException    When the table already exists
     * @throws \DomainException When an unsupported PDO driver is used
     */
    public function createTable()
    {
        // connect if we are not yet
        $this->getConnection();

        switch ($this->driver) {
            case 'mysql':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR(128) NOT NULL PRIMARY KEY, $this->dataCol BLOB NOT NULL, $this->lifetimeCol MEDIUMINT NOT NULL, $this->timeCol INTEGER NOT NULL) ENGINE = InnoDB";
                break;
            case 'sqlite':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR(128) NOT NULL PRIMARY KEY, $this->dataCol BLOB NOT NULL, $this->lifetimeCol MEDIUMINT NOT NULL, $this->timeCol INTEGER NOT NULL)";
                break;
            case 'pgsql':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR(128) NOT NULL PRIMARY KEY, $this->dataCol BYTEA NOT NULL, $this->lifetimeCol INTEGER NOT NULL, $this->timeCol INTEGER NOT NULL)";
                break;
            case 'oci':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR2(128) NOT NULL PRIMARY KEY, $this->dataCol BLOB NOT NULL, $this->lifetimeCol INTEGER NOT NULL, $this->timeCol INTEGER NOT NULL)";
                break;
            case 'sqlsrv':
                $sql = "CREATE TABLE $this->table ($this->idCol VARCHAR(128) NOT NULL PRIMARY KEY, $this->dataCol VARBINARY(MAX) NOT NULL, $this->lifetimeCol INTEGER NOT NULL, $this->timeCol INTEGER NOT NULL)";
                break;
            default:
                throw new \DomainException(sprintf('"%s" does not currently support PDO driver "%s".', __CLASS__, $this->driver));
        }

        try {
            $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            $this->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        if (null === $this->pdo) {
            $this->connect($this->dsn ?: $savePath);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        $this->beginTransaction();

        try {
            $this->lockSession($sessionId);

            // We need to make sure we do not return session data that is already considered garbage according
            // to the session.gc_maxlifetime setting because gc() is called after read() and only sometimes.

            $sql = "SELECT $this->dataCol FROM $this->table WHERE $this->idCol = :id AND $this->lifetimeCol + $this->timeCol >= :time";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
            $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
            $stmt->execute();

            // We use fetchAll instead of fetchColumn to make sure the DB cursor gets closed
            $sessionRows = $stmt->fetchAll(\PDO::FETCH_NUM);

            return $sessionRows ? $sessionRows[0][0] : '';
        } catch (\PDOException $e) {
            $this->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        // We delay gc() to close() so that it is executed outside the transactional and blocking read-write process.
        // This way, pruning expired sessions does not block them from being started while the current session is used.
        $this->gcCalled = true;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        // delete the record associated with this id
        $sql = "DELETE FROM $this->table WHERE $this->idCol = :id";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->rollback();

            throw $e;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        // The session ID can be different from the one previously received in read()
        // when the session ID changed due to session_regenerate_id(). So we have to
        // do an insert or update even if we created a row in read() for locking.
        // We use a single MERGE SQL query when supported by the database.

        $maxlifetime = (int) ini_get('session.gc_maxlifetime');

        try {
            $mergeSql = $this->getMergeSql();

            if (null !== $mergeSql) {
                $mergeStmt = $this->pdo->prepare($mergeSql);
                $mergeStmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
                $mergeStmt->bindParam(':data', $data, \PDO::PARAM_LOB);
                $mergeStmt->bindParam(':lifetime', $maxlifetime, \PDO::PARAM_INT);
                $mergeStmt->bindValue(':time', time(), \PDO::PARAM_INT);
                $mergeStmt->execute();

                return true;
            }

            $updateStmt = $this->pdo->prepare(
                "UPDATE $this->table SET $this->dataCol = :data, $this->lifetimeCol = :lifetime, $this->timeCol = :time WHERE $this->idCol = :id"
            );
            $updateStmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
            $updateStmt->bindParam(':data', $data, \PDO::PARAM_LOB);
            $updateStmt->bindParam(':lifetime', $maxlifetime, \PDO::PARAM_INT);
            $updateStmt->bindValue(':time', time(), \PDO::PARAM_INT);
            $updateStmt->execute();

            // When MERGE is not supported, like in Postgres, we have to use this approach that can result in
            // duplicate key errors when the same session is written simultaneously. We can just catch such an
            // error and re-execute the update. This is similar to a serializable transaction with retry logic
            // on serialization failures but without the overhead and without possible false positives due to
            // longer gap locking.
            // Since we have a lock on the session, the above case should not happen. And if it's a regenerated
            // session ID it should be unique anyway.
            if (!$updateStmt->rowCount()) {
                try {
                    $insertStmt = $this->pdo->prepare(
                        "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time)"
                    );
                    $insertStmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
                    $insertStmt->bindParam(':data', $data, \PDO::PARAM_LOB);
                    $insertStmt->bindParam(':lifetime', $maxlifetime, \PDO::PARAM_INT);
                    $insertStmt->bindValue(':time', time(), \PDO::PARAM_INT);
                    $insertStmt->execute();
                } catch (\PDOException $e) {
                    // Handle integrity violation SQLSTATE 23000 (or a subclass like 23505 in Postgres) for duplicate keys
                    if (0 === strpos($e->getCode(), '23')) {
                        $updateStmt->execute();
                    } else {
                        throw $e;
                    }
                }
            }
        } catch (\PDOException $e) {
            $this->rollback();

            throw $e;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->commit();

        if ($this->gcCalled) {
            $this->gcCalled = false;

            // delete the session records that have expired
            $sql = "DELETE FROM $this->table WHERE $this->lifetimeCol + $this->timeCol < :time";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
            $stmt->execute();
        }

        if (false !== $this->dsn) {
            $this->pdo = null;
        }

        return true;
    }

    /**
     * Lazy-connects to the database.
     *
     * @param string $dsn DSN string
     */
    private function connect($dsn)
    {
        $this->pdo = new \PDO($dsn, $this->username, $this->password, $this->connectionOptions);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Helper method to begin a transaction.
     *
     * Since SQLite does not support row level locks, we have to acquire a reserved lock
     * on the database immediately. Because of https://bugs.php.net/42766 we have to create
     * such a transaction manually which also means we cannot use PDO::commit or
     * PDO::rollback or PDO::inTransaction for SQLite.
     */
    private function beginTransaction()
    {
        if (!$this->inTransaction) {
            if ('sqlite' === $this->driver) {
                $this->pdo->exec('BEGIN IMMEDIATE TRANSACTION');
            } else {
                $this->pdo->beginTransaction();
            }
            $this->inTransaction = true;
        }
    }

    /**
     * Helper method to commit a transaction.
     */
    private function commit()
    {
        if ($this->inTransaction) {
            try {
                // commit read-write transaction which also releases the lock
                if ('sqlite' === $this->driver) {
                    $this->pdo->exec('COMMIT');
                } else {
                    $this->pdo->commit();
                }
                $this->inTransaction = false;
            } catch (\PDOException $e) {
                $this->rollback();

                throw $e;
            }
        }
    }

    /**
     * Helper method to rollback a transaction.
     */
    private function rollback()
    {
        // We only need to rollback if we are in a transaction. Otherwise the resulting
        // error would hide the real problem why rollback was called. We might not be
        // in a transaction when two callbacks (e.g. destroy and write) are invoked that
        // both fail.
        if ($this->inTransaction) {
            if ('sqlite' === $this->driver) {
                $this->pdo->exec('ROLLBACK');
            } else {
                $this->pdo->rollback();
            }
            $this->inTransaction = false;
        }
    }

    /**
     * Exclusively locks the row so other concurrent requests on the same session will block.
     *
     * This prevents loss of data by keeping the data consistent between read() and write().
     * We do not use SELECT FOR UPDATE because it does not lock non-existent rows. And a following
     * INSERT when not found can result in a deadlock for one connection.
     *
     * @param string $sessionId Session ID
     *
     * @throws \DomainException When an unsupported PDO driver is used
     */
    private function lockSession($sessionId)
    {
        switch ($this->driver) {
            case 'mysql':
                // will also lock the row when actually nothing got updated (id = id)
                $sql = "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time) ".
                    "ON DUPLICATE KEY UPDATE $this->idCol = $this->idCol";
                break;
            case 'oci':
                // DUAL is Oracle specific dummy table
                $sql = "MERGE INTO $this->table USING DUAL ON ($this->idCol = :id) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time) ".
                    "WHEN MATCHED THEN UPDATE SET $this->idCol = $this->idCol";
                break;
            // todo: implement locking for SQL Server < 2008
            case 'sqlsrv' === $this->driver && version_compare($this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '10', '>='):
                // MS SQL Server requires MERGE be terminated by semicolon
                $sql = "MERGE INTO $this->table WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON ($this->idCol = :id) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time) ".
                    "WHEN MATCHED THEN UPDATE SET $this->idCol = $this->idCol;";
                break;
            case 'pgsql':
                // obtain an exclusive transaction level advisory lock
                $sql = 'SELECT pg_advisory_xact_lock(:lock_id)';
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':lock_id', hexdec(substr($sessionId, 0, 15)), \PDO::PARAM_INT);
                $stmt->execute();

                return;
            case 'sqlite':
                return; // we already locked when starting transaction
            default:
                throw new \DomainException(sprintf('"%s" does not currently support PDO driver "%s".', __CLASS__, $this->driver));
        }

        // We create a DML lock for the session by inserting empty data or updating the row.
        // This is safer than an application level advisory lock because it also prevents concurrent modification
        // of the session from other parts of the application.
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
        $stmt->bindValue(':data', '', \PDO::PARAM_STR);
        $stmt->bindValue(':lifetime', 0, \PDO::PARAM_INT);
        $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Returns a merge/upsert (i.e. insert or update) SQL query when supported by the database.
     *
     * @return string|null The SQL string or null when not supported
     */
    private function getMergeSql()
    {
        switch ($this->driver) {
            case 'mysql':
                return "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time) ".
                    "ON DUPLICATE KEY UPDATE $this->dataCol = VALUES($this->dataCol), $this->lifetimeCol = VALUES($this->lifetimeCol), $this->timeCol = VALUES($this->timeCol)";
            case 'oci':
                // DUAL is Oracle specific dummy table
                return "MERGE INTO $this->table USING DUAL ON ($this->idCol = :id) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = :data, $this->lifetimeCol = :lifetime, $this->timeCol = :time";
            case 'sqlsrv' === $this->driver && version_compare($this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '10', '>='):
                // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                // It also requires HOLDLOCK according to http://weblogs.sqlteam.com/dang/archive/2009/01/31/UPSERT-Race-Condition-With-MERGE.aspx
                return "MERGE INTO $this->table WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON ($this->idCol = :id) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = :data, $this->lifetimeCol = :lifetime, $this->timeCol = :time;";
            case 'sqlite':
                return "INSERT OR REPLACE INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time)";
        }
    }

    /**
     * Return a PDO instance
     *
     * @return \PDO
     */
    protected function getConnection()
    {
        if (null === $this->pdo) {
            $this->connect($this->dsn ?: ini_get('session.save_path'));
        }

        return $this->pdo;
    }
}
