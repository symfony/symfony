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
 * different locking strategies to handle concurrent access to the same session.
 * Locking is necessary to prevent loss of data due to race conditions and to keep
 * the session data consistent between read() and write(). With locking, requests
 * for the same session will wait until the other one finished writing. For this
 * reason it's best practice to close a session as early as possible to improve
 * concurrency. PHPs internal files session handler also implements locking.
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
 * @see https://php.net/sessionhandlerinterface
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Michael Williams <michael.williams@funsational.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class PdoSessionHandler extends AbstractSessionHandler
{
    /**
     * No locking is done. This means sessions are prone to loss of data due to
     * race conditions of concurrent requests to the same session. The last session
     * write will win in this case. It might be useful when you implement your own
     * logic to deal with this like an optimistic approach.
     */
    const LOCK_NONE = 0;

    /**
     * Creates an application-level lock on a session. The disadvantage is that the
     * lock is not enforced by the database and thus other, unaware parts of the
     * application could still concurrently modify the session. The advantage is it
     * does not require a transaction.
     * This mode is not available for SQLite and not yet implemented for oci and sqlsrv.
     */
    const LOCK_ADVISORY = 1;

    /**
     * Issues a real row lock. Since it uses a transaction between opening and
     * closing a session, you have to be careful when you use same database connection
     * that you also use for your application logic. This mode is the default because
     * it's the only reliable solution across DBMSs.
     */
    const LOCK_TRANSACTIONAL = 2;

    /**
     * @var \PDO|null PDO instance or null when not connected yet
     */
    private $pdo;

    /**
     * @var string|false|null DSN string or null for session.save_path or false when lazy connection disabled
     */
    private $dsn = false;

    /**
     * @var string Database driver
     */
    private $driver;

    /**
     * @var string Table name
     */
    private $table = 'sessions';

    /**
     * @var string Column for session id
     */
    private $idCol = 'sess_id';

    /**
     * @var string Column for session data
     */
    private $dataCol = 'sess_data';

    /**
     * @var string Column for lifetime
     */
    private $lifetimeCol = 'sess_lifetime';

    /**
     * @var string Column for timestamp
     */
    private $timeCol = 'sess_time';

    /**
     * @var string Username when lazy-connect
     */
    private $username = '';

    /**
     * @var string Password when lazy-connect
     */
    private $password = '';

    /**
     * @var array Connection options when lazy-connect
     */
    private $connectionOptions = [];

    /**
     * @var int The strategy for locking, see constants
     */
    private $lockMode = self::LOCK_TRANSACTIONAL;

    /**
     * It's an array to support multiple reads before closing which is manual, non-standard usage.
     *
     * @var \PDOStatement[] An array of statements to release advisory locks
     */
    private $unlockStatements = [];

    /**
     * @var bool True when the current session exists but expired according to session.gc_maxlifetime
     */
    private $sessionExpired = false;

    /**
     * @var bool Whether a transaction is active
     */
    private $inTransaction = false;

    /**
     * @var bool Whether gc() has been called
     */
    private $gcCalled = false;

    /**
     * You can either pass an existing database connection as PDO instance or
     * pass a DSN string that will be used to lazy-connect to the database
     * when the session is actually used. Furthermore it's possible to pass null
     * which will then use the session.save_path ini setting as PDO DSN parameter.
     *
     * List of available options:
     *  * db_table: The name of the table [default: sessions]
     *  * db_id_col: The column where to store the session id [default: sess_id]
     *  * db_data_col: The column where to store the session data [default: sess_data]
     *  * db_lifetime_col: The column where to store the lifetime [default: sess_lifetime]
     *  * db_time_col: The column where to store the timestamp [default: sess_time]
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: []]
     *  * lock_mode: The strategy for locking, see constants [default: LOCK_TRANSACTIONAL]
     *
     * @param \PDO|string|null $pdoOrDsn A \PDO instance or DSN string or URL string or null
     * @param array            $options  An associative array of options
     *
     * @throws \InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     */
    public function __construct($pdoOrDsn = null, array $options = [])
    {
        if ($pdoOrDsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $pdoOrDsn->getAttribute(\PDO::ATTR_ERRMODE)) {
                throw new \InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION))', __CLASS__));
            }

            $this->pdo = $pdoOrDsn;
            $this->driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } elseif (\is_string($pdoOrDsn) && false !== strpos($pdoOrDsn, '://')) {
            $this->dsn = $this->buildDsnFromUrl($pdoOrDsn);
        } else {
            $this->dsn = $pdoOrDsn;
        }

        $this->table = isset($options['db_table']) ? $options['db_table'] : $this->table;
        $this->idCol = isset($options['db_id_col']) ? $options['db_id_col'] : $this->idCol;
        $this->dataCol = isset($options['db_data_col']) ? $options['db_data_col'] : $this->dataCol;
        $this->lifetimeCol = isset($options['db_lifetime_col']) ? $options['db_lifetime_col'] : $this->lifetimeCol;
        $this->timeCol = isset($options['db_time_col']) ? $options['db_time_col'] : $this->timeCol;
        $this->username = isset($options['db_username']) ? $options['db_username'] : $this->username;
        $this->password = isset($options['db_password']) ? $options['db_password'] : $this->password;
        $this->connectionOptions = isset($options['db_connection_options']) ? $options['db_connection_options'] : $this->connectionOptions;
        $this->lockMode = isset($options['lock_mode']) ? $options['lock_mode'] : $this->lockMode;
    }

    /**
     * Creates the table to store sessions which can be called once for setup.
     *
     * Session ID is saved in a column of maximum length 128 because that is enough even
     * for a 512 bit configured session.hash_function like Whirlpool. Session data is
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
                // We use varbinary for the ID column because it prevents unwanted conversions:
                // - character set conversions between server and client
                // - trailing space removal
                // - case-insensitivity
                // - language processing like é == e
                $sql = "CREATE TABLE $this->table ($this->idCol VARBINARY(128) NOT NULL PRIMARY KEY, $this->dataCol BLOB NOT NULL, $this->lifetimeCol INTEGER UNSIGNED NOT NULL, $this->timeCol INTEGER UNSIGNED NOT NULL) COLLATE utf8mb4_bin, ENGINE = InnoDB";
                break;
            case 'sqlite':
                $sql = "CREATE TABLE $this->table ($this->idCol TEXT NOT NULL PRIMARY KEY, $this->dataCol BLOB NOT NULL, $this->lifetimeCol INTEGER NOT NULL, $this->timeCol INTEGER NOT NULL)";
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
                throw new \DomainException(sprintf('Creating the session table is currently not implemented for PDO driver "%s".', $this->driver));
        }

        try {
            $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            $this->rollback();

            throw $e;
        }
    }

    /**
     * Returns true when the current session exists but expired according to session.gc_maxlifetime.
     *
     * Can be used to distinguish between a new session and one that expired due to inactivity.
     *
     * @return bool Whether current session expired
     */
    public function isSessionExpired()
    {
        return $this->sessionExpired;
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        $this->sessionExpired = false;

        if (null === $this->pdo) {
            $this->connect($this->dsn ?: $savePath);
        }

        return parent::open($savePath, $sessionName);
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        try {
            return parent::read($sessionId);
        } catch (\PDOException $e) {
            $this->rollback();

            throw $e;
        }
    }

    /**
     * @return bool
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
    protected function doDestroy($sessionId)
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
    protected function doWrite($sessionId, $data)
    {
        $maxlifetime = (int) ini_get('session.gc_maxlifetime');

        try {
            // We use a single MERGE SQL query when supported by the database.
            $mergeStmt = $this->getMergeStatement($sessionId, $data, $maxlifetime);
            if (null !== $mergeStmt) {
                $mergeStmt->execute();

                return true;
            }

            $updateStmt = $this->getUpdateStatement($sessionId, $data, $maxlifetime);
            $updateStmt->execute();

            // When MERGE is not supported, like in Postgres < 9.5, we have to use this approach that can result in
            // duplicate key errors when the same session is written simultaneously (given the LOCK_NONE behavior).
            // We can just catch such an error and re-execute the update. This is similar to a serializable
            // transaction with retry logic on serialization failures but without the overhead and without possible
            // false positives due to longer gap locking.
            if (!$updateStmt->rowCount()) {
                try {
                    $insertStmt = $this->getInsertStatement($sessionId, $data, $maxlifetime);
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
    public function updateTimestamp($sessionId, $data)
    {
        $maxlifetime = (int) ini_get('session.gc_maxlifetime');

        try {
            $updateStmt = $this->pdo->prepare(
                "UPDATE $this->table SET $this->lifetimeCol = :lifetime, $this->timeCol = :time WHERE $this->idCol = :id"
            );
            $updateStmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
            $updateStmt->bindParam(':lifetime', $maxlifetime, \PDO::PARAM_INT);
            $updateStmt->bindValue(':time', time(), \PDO::PARAM_INT);
            $updateStmt->execute();
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

        while ($unlockStmt = array_shift($this->unlockStatements)) {
            $unlockStmt->execute();
        }

        if ($this->gcCalled) {
            $this->gcCalled = false;

            // delete the session records that have expired
            if ('mysql' === $this->driver) {
                $sql = "DELETE FROM $this->table WHERE $this->lifetimeCol + $this->timeCol < :time";
            } else {
                $sql = "DELETE FROM $this->table WHERE $this->lifetimeCol < :time - $this->timeCol";
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
            $stmt->execute();
        }

        if (false !== $this->dsn) {
            $this->pdo = null; // only close lazy-connection
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
     * Builds a PDO DSN from a URL-like connection string.
     *
     * @param string $dsnOrUrl
     *
     * @return string
     *
     * @todo implement missing support for oci DSN (which look totally different from other PDO ones)
     */
    private function buildDsnFromUrl($dsnOrUrl)
    {
        // (pdo_)?sqlite3?:///... => (pdo_)?sqlite3?://localhost/... or else the URL will be invalid
        $url = preg_replace('#^((?:pdo_)?sqlite3?):///#', '$1://localhost/', $dsnOrUrl);

        $params = parse_url($url);

        if (false === $params) {
            return $dsnOrUrl; // If the URL is not valid, let's assume it might be a DSN already.
        }

        $params = array_map('rawurldecode', $params);

        // Override the default username and password. Values passed through options will still win over these in the constructor.
        if (isset($params['user'])) {
            $this->username = $params['user'];
        }

        if (isset($params['pass'])) {
            $this->password = $params['pass'];
        }

        if (!isset($params['scheme'])) {
            throw new \InvalidArgumentException('URLs without scheme are not supported to configure the PdoSessionHandler');
        }

        $driverAliasMap = [
            'mssql' => 'sqlsrv',
            'mysql2' => 'mysql', // Amazon RDS, for some weird reason
            'postgres' => 'pgsql',
            'postgresql' => 'pgsql',
            'sqlite3' => 'sqlite',
        ];

        $driver = isset($driverAliasMap[$params['scheme']]) ? $driverAliasMap[$params['scheme']] : $params['scheme'];

        // Doctrine DBAL supports passing its internal pdo_* driver names directly too (allowing both dashes and underscores). This allows supporting the same here.
        if (0 === strpos($driver, 'pdo_') || 0 === strpos($driver, 'pdo-')) {
            $driver = substr($driver, 4);
        }

        switch ($driver) {
            case 'mysql':
            case 'pgsql':
                $dsn = $driver.':';

                if (isset($params['host']) && '' !== $params['host']) {
                    $dsn .= 'host='.$params['host'].';';
                }

                if (isset($params['port']) && '' !== $params['port']) {
                    $dsn .= 'port='.$params['port'].';';
                }

                if (isset($params['path'])) {
                    $dbName = substr($params['path'], 1); // Remove the leading slash
                    $dsn .= 'dbname='.$dbName.';';
                }

                return $dsn;

            case 'sqlite':
                return 'sqlite:'.substr($params['path'], 1);

            case 'sqlsrv':
                $dsn = 'sqlsrv:server=';

                if (isset($params['host'])) {
                    $dsn .= $params['host'];
                }

                if (isset($params['port']) && '' !== $params['port']) {
                    $dsn .= ','.$params['port'];
                }

                if (isset($params['path'])) {
                    $dbName = substr($params['path'], 1); // Remove the leading slash
                    $dsn .= ';Database='.$dbName;
                }

                return $dsn;

            default:
                throw new \InvalidArgumentException(sprintf('The scheme "%s" is not supported by the PdoSessionHandler URL configuration. Pass a PDO DSN directly.', $params['scheme']));
        }
    }

    /**
     * Helper method to begin a transaction.
     *
     * Since SQLite does not support row level locks, we have to acquire a reserved lock
     * on the database immediately. Because of https://bugs.php.net/42766 we have to create
     * such a transaction manually which also means we cannot use PDO::commit or
     * PDO::rollback or PDO::inTransaction for SQLite.
     *
     * Also MySQLs default isolation, REPEATABLE READ, causes deadlock for different sessions
     * due to https://percona.com/blog/2013/12/12/one-more-innodb-gap-lock-to-avoid/ .
     * So we change it to READ COMMITTED.
     */
    private function beginTransaction()
    {
        if (!$this->inTransaction) {
            if ('sqlite' === $this->driver) {
                $this->pdo->exec('BEGIN IMMEDIATE TRANSACTION');
            } else {
                if ('mysql' === $this->driver) {
                    $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                }
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
        // in a transaction when not using the transactional locking behavior or when
        // two callbacks (e.g. destroy and write) are invoked that both fail.
        if ($this->inTransaction) {
            if ('sqlite' === $this->driver) {
                $this->pdo->exec('ROLLBACK');
            } else {
                $this->pdo->rollBack();
            }
            $this->inTransaction = false;
        }
    }

    /**
     * Reads the session data in respect to the different locking strategies.
     *
     * We need to make sure we do not return session data that is already considered garbage according
     * to the session.gc_maxlifetime setting because gc() is called after read() and only sometimes.
     *
     * @param string $sessionId Session ID
     *
     * @return string The session data
     */
    protected function doRead($sessionId)
    {
        if (self::LOCK_ADVISORY === $this->lockMode) {
            $this->unlockStatements[] = $this->doAdvisoryLock($sessionId);
        }

        $selectSql = $this->getSelectSql();
        $selectStmt = $this->pdo->prepare($selectSql);
        $selectStmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
        $insertStmt = null;

        do {
            $selectStmt->execute();
            $sessionRows = $selectStmt->fetchAll(\PDO::FETCH_NUM);

            if ($sessionRows) {
                if ($sessionRows[0][1] + $sessionRows[0][2] < time()) {
                    $this->sessionExpired = true;

                    return '';
                }

                return \is_resource($sessionRows[0][0]) ? stream_get_contents($sessionRows[0][0]) : $sessionRows[0][0];
            }

            if (null !== $insertStmt) {
                $this->rollback();
                throw new \RuntimeException('Failed to read session: INSERT reported a duplicate id but next SELECT did not return any data.');
            }

            if (!filter_var(ini_get('session.use_strict_mode'), FILTER_VALIDATE_BOOLEAN) && self::LOCK_TRANSACTIONAL === $this->lockMode && 'sqlite' !== $this->driver) {
                // In strict mode, session fixation is not possible: new sessions always start with a unique
                // random id, so that concurrency is not possible and this code path can be skipped.
                // Exclusive-reading of non-existent rows does not block, so we need to do an insert to block
                // until other connections to the session are committed.
                try {
                    $insertStmt = $this->getInsertStatement($sessionId, '', 0);
                    $insertStmt->execute();
                } catch (\PDOException $e) {
                    // Catch duplicate key error because other connection created the session already.
                    // It would only not be the case when the other connection destroyed the session.
                    if (0 === strpos($e->getCode(), '23')) {
                        // Retrieve finished session data written by concurrent connection by restarting the loop.
                        // We have to start a new transaction as a failed query will mark the current transaction as
                        // aborted in PostgreSQL and disallow further queries within it.
                        $this->rollback();
                        $this->beginTransaction();
                        continue;
                    }

                    throw $e;
                }
            }

            return '';
        } while (true);
    }

    /**
     * Executes an application-level lock on the database.
     *
     * @param string $sessionId Session ID
     *
     * @return \PDOStatement The statement that needs to be executed later to release the lock
     *
     * @throws \DomainException When an unsupported PDO driver is used
     *
     * @todo implement missing advisory locks
     *       - for oci using DBMS_LOCK.REQUEST
     *       - for sqlsrv using sp_getapplock with LockOwner = Session
     */
    private function doAdvisoryLock($sessionId)
    {
        switch ($this->driver) {
            case 'mysql':
                // MySQL 5.7.5 and later enforces a maximum length on lock names of 64 characters. Previously, no limit was enforced.
                $lockId = substr($sessionId, 0, 64);
                // should we handle the return value? 0 on timeout, null on error
                // we use a timeout of 50 seconds which is also the default for innodb_lock_wait_timeout
                $stmt = $this->pdo->prepare('SELECT GET_LOCK(:key, 50)');
                $stmt->bindValue(':key', $lockId, \PDO::PARAM_STR);
                $stmt->execute();

                $releaseStmt = $this->pdo->prepare('DO RELEASE_LOCK(:key)');
                $releaseStmt->bindValue(':key', $lockId, \PDO::PARAM_STR);

                return $releaseStmt;
            case 'pgsql':
                // Obtaining an exclusive session level advisory lock requires an integer key.
                // When session.sid_bits_per_character > 4, the session id can contain non-hex-characters.
                // So we cannot just use hexdec().
                if (4 === \PHP_INT_SIZE) {
                    $sessionInt1 = $this->convertStringToInt($sessionId);
                    $sessionInt2 = $this->convertStringToInt(substr($sessionId, 4, 4));

                    $stmt = $this->pdo->prepare('SELECT pg_advisory_lock(:key1, :key2)');
                    $stmt->bindValue(':key1', $sessionInt1, \PDO::PARAM_INT);
                    $stmt->bindValue(':key2', $sessionInt2, \PDO::PARAM_INT);
                    $stmt->execute();

                    $releaseStmt = $this->pdo->prepare('SELECT pg_advisory_unlock(:key1, :key2)');
                    $releaseStmt->bindValue(':key1', $sessionInt1, \PDO::PARAM_INT);
                    $releaseStmt->bindValue(':key2', $sessionInt2, \PDO::PARAM_INT);
                } else {
                    $sessionBigInt = $this->convertStringToInt($sessionId);

                    $stmt = $this->pdo->prepare('SELECT pg_advisory_lock(:key)');
                    $stmt->bindValue(':key', $sessionBigInt, \PDO::PARAM_INT);
                    $stmt->execute();

                    $releaseStmt = $this->pdo->prepare('SELECT pg_advisory_unlock(:key)');
                    $releaseStmt->bindValue(':key', $sessionBigInt, \PDO::PARAM_INT);
                }

                return $releaseStmt;
            case 'sqlite':
                throw new \DomainException('SQLite does not support advisory locks.');
            default:
                throw new \DomainException(sprintf('Advisory locks are currently not implemented for PDO driver "%s".', $this->driver));
        }
    }

    /**
     * Encodes the first 4 (when PHP_INT_SIZE == 4) or 8 characters of the string as an integer.
     *
     * Keep in mind, PHP integers are signed.
     *
     * @param string $string
     *
     * @return int
     */
    private function convertStringToInt($string)
    {
        if (4 === \PHP_INT_SIZE) {
            return (\ord($string[3]) << 24) + (\ord($string[2]) << 16) + (\ord($string[1]) << 8) + \ord($string[0]);
        }

        $int1 = (\ord($string[7]) << 24) + (\ord($string[6]) << 16) + (\ord($string[5]) << 8) + \ord($string[4]);
        $int2 = (\ord($string[3]) << 24) + (\ord($string[2]) << 16) + (\ord($string[1]) << 8) + \ord($string[0]);

        return $int2 + ($int1 << 32);
    }

    /**
     * Return a locking or nonlocking SQL query to read session information.
     *
     * @return string The SQL string
     *
     * @throws \DomainException When an unsupported PDO driver is used
     */
    private function getSelectSql()
    {
        if (self::LOCK_TRANSACTIONAL === $this->lockMode) {
            $this->beginTransaction();

            switch ($this->driver) {
                case 'mysql':
                case 'oci':
                case 'pgsql':
                    return "SELECT $this->dataCol, $this->lifetimeCol, $this->timeCol FROM $this->table WHERE $this->idCol = :id FOR UPDATE";
                case 'sqlsrv':
                    return "SELECT $this->dataCol, $this->lifetimeCol, $this->timeCol FROM $this->table WITH (UPDLOCK, ROWLOCK) WHERE $this->idCol = :id";
                case 'sqlite':
                    // we already locked when starting transaction
                    break;
                default:
                    throw new \DomainException(sprintf('Transactional locks are currently not implemented for PDO driver "%s".', $this->driver));
            }
        }

        return "SELECT $this->dataCol, $this->lifetimeCol, $this->timeCol FROM $this->table WHERE $this->idCol = :id";
    }

    /**
     * Returns an insert statement supported by the database for writing session data.
     *
     * @param string $sessionId   Session ID
     * @param string $sessionData Encoded session data
     * @param int    $maxlifetime session.gc_maxlifetime
     *
     * @return \PDOStatement The insert statement
     */
    private function getInsertStatement($sessionId, $sessionData, $maxlifetime)
    {
        switch ($this->driver) {
            case 'oci':
                $data = fopen('php://memory', 'r+');
                fwrite($data, $sessionData);
                rewind($data);
                $sql = "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, EMPTY_BLOB(), :lifetime, :time) RETURNING $this->dataCol into :data";
                break;
            default:
                $data = $sessionData;
                $sql = "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time)";
                break;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
        $stmt->bindParam(':data', $data, \PDO::PARAM_LOB);
        $stmt->bindParam(':lifetime', $maxlifetime, \PDO::PARAM_INT);
        $stmt->bindValue(':time', time(), \PDO::PARAM_INT);

        return $stmt;
    }

    /**
     * Returns an update statement supported by the database for writing session data.
     *
     * @param string $sessionId   Session ID
     * @param string $sessionData Encoded session data
     * @param int    $maxlifetime session.gc_maxlifetime
     *
     * @return \PDOStatement The update statement
     */
    private function getUpdateStatement($sessionId, $sessionData, $maxlifetime)
    {
        switch ($this->driver) {
            case 'oci':
                $data = fopen('php://memory', 'r+');
                fwrite($data, $sessionData);
                rewind($data);
                $sql = "UPDATE $this->table SET $this->dataCol = EMPTY_BLOB(), $this->lifetimeCol = :lifetime, $this->timeCol = :time WHERE $this->idCol = :id RETURNING $this->dataCol into :data";
                break;
            default:
                $data = $sessionData;
                $sql = "UPDATE $this->table SET $this->dataCol = :data, $this->lifetimeCol = :lifetime, $this->timeCol = :time WHERE $this->idCol = :id";
                break;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
        $stmt->bindParam(':data', $data, \PDO::PARAM_LOB);
        $stmt->bindParam(':lifetime', $maxlifetime, \PDO::PARAM_INT);
        $stmt->bindValue(':time', time(), \PDO::PARAM_INT);

        return $stmt;
    }

    /**
     * Returns a merge/upsert (i.e. insert or update) statement when supported by the database for writing session data.
     *
     * @param string $sessionId   Session ID
     * @param string $data        Encoded session data
     * @param int    $maxlifetime session.gc_maxlifetime
     *
     * @return \PDOStatement|null The merge statement or null when not supported
     */
    private function getMergeStatement($sessionId, $data, $maxlifetime)
    {
        switch (true) {
            case 'mysql' === $this->driver:
                $mergeSql = "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time) ".
                    "ON DUPLICATE KEY UPDATE $this->dataCol = VALUES($this->dataCol), $this->lifetimeCol = VALUES($this->lifetimeCol), $this->timeCol = VALUES($this->timeCol)";
                break;
            case 'sqlsrv' === $this->driver && version_compare($this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '10', '>='):
                // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                // It also requires HOLDLOCK according to https://weblogs.sqlteam.com/dang/2009/01/31/upsert-race-condition-with-merge/
                $mergeSql = "MERGE INTO $this->table WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON ($this->idCol = ?) ".
                    "WHEN NOT MATCHED THEN INSERT ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (?, ?, ?, ?) ".
                    "WHEN MATCHED THEN UPDATE SET $this->dataCol = ?, $this->lifetimeCol = ?, $this->timeCol = ?;";
                break;
            case 'sqlite' === $this->driver:
                $mergeSql = "INSERT OR REPLACE INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time)";
                break;
            case 'pgsql' === $this->driver && version_compare($this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '9.5', '>='):
                $mergeSql = "INSERT INTO $this->table ($this->idCol, $this->dataCol, $this->lifetimeCol, $this->timeCol) VALUES (:id, :data, :lifetime, :time) ".
                    "ON CONFLICT ($this->idCol) DO UPDATE SET ($this->dataCol, $this->lifetimeCol, $this->timeCol) = (EXCLUDED.$this->dataCol, EXCLUDED.$this->lifetimeCol, EXCLUDED.$this->timeCol)";
                break;
            default:
                // MERGE is not supported with LOBs: https://oracle.com/technetwork/articles/fuecks-lobs-095315.html
                return null;
        }

        $mergeStmt = $this->pdo->prepare($mergeSql);

        if ('sqlsrv' === $this->driver) {
            $mergeStmt->bindParam(1, $sessionId, \PDO::PARAM_STR);
            $mergeStmt->bindParam(2, $sessionId, \PDO::PARAM_STR);
            $mergeStmt->bindParam(3, $data, \PDO::PARAM_LOB);
            $mergeStmt->bindParam(4, $maxlifetime, \PDO::PARAM_INT);
            $mergeStmt->bindValue(5, time(), \PDO::PARAM_INT);
            $mergeStmt->bindParam(6, $data, \PDO::PARAM_LOB);
            $mergeStmt->bindParam(7, $maxlifetime, \PDO::PARAM_INT);
            $mergeStmt->bindValue(8, time(), \PDO::PARAM_INT);
        } else {
            $mergeStmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
            $mergeStmt->bindParam(':data', $data, \PDO::PARAM_LOB);
            $mergeStmt->bindParam(':lifetime', $maxlifetime, \PDO::PARAM_INT);
            $mergeStmt->bindValue(':time', time(), \PDO::PARAM_INT);
        }

        return $mergeStmt;
    }

    /**
     * Return a PDO instance.
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
