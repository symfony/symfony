<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Security\RememberMe;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentTokenInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

/**
 * This class provides storage for the tokens that is set in "remember me"
 * cookies. This way no password secrets will be stored in the cookies on
 * the client machine, and thus the security is improved.
 *
 * This depends only on doctrine in order to get a database connection
 * and to do the conversion of the datetime column.
 *
 * In order to use this class, you need the following table in your database:
 *
 *     CREATE TABLE `rememberme_token` (
 *         `series`   char(88)     UNIQUE PRIMARY KEY NOT NULL,
 *         `value`    char(88)     NOT NULL,
 *         `lastUsed` datetime     NOT NULL,
 *         `class`    varchar(100) NOT NULL,
 *         `username` varchar(200) NOT NULL
 *     );
 */
class DoctrineTokenProvider implements TokenProviderInterface
{
    private $conn;

    private static $useDeprecatedConstants;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;

        if (null === self::$useDeprecatedConstants) {
            self::$useDeprecatedConstants = !class_exists(Types::class);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function loadTokenBySeries(string $series)
    {
        // the alias for lastUsed works around case insensitivity in PostgreSQL
        $sql = 'SELECT class, username, value, lastUsed AS last_used'
            .' FROM rememberme_token WHERE series=:series';
        $paramValues = ['series' => $series];
        $paramTypes = ['series' => \PDO::PARAM_STR];
        $stmt = $this->conn->executeQuery($sql, $paramValues, $paramTypes);
        $row = $stmt instanceof Result || $stmt instanceof DriverResult ? $stmt->fetchAssociative() : $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return new PersistentToken($row['class'], $row['username'], $series, $row['value'], new \DateTime($row['last_used']));
        }

        throw new TokenNotFoundException('No token found.');
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTokenBySeries(string $series)
    {
        $sql = 'DELETE FROM rememberme_token WHERE series=:series';
        $paramValues = ['series' => $series];
        $paramTypes = ['series' => \PDO::PARAM_STR];
        if (method_exists($this->conn, 'executeStatement')) {
            $this->conn->executeStatement($sql, $paramValues, $paramTypes);
        } else {
            $this->conn->executeUpdate($sql, $paramValues, $paramTypes);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateToken(string $series, string $tokenValue, \DateTime $lastUsed)
    {
        $sql = 'UPDATE rememberme_token SET value=:value, lastUsed=:lastUsed'
            .' WHERE series=:series';
        $paramValues = [
            'value' => $tokenValue,
            'lastUsed' => $lastUsed,
            'series' => $series,
        ];
        $paramTypes = [
            'value' => \PDO::PARAM_STR,
            'lastUsed' => self::$useDeprecatedConstants ? Type::DATETIME : Types::DATETIME_MUTABLE,
            'series' => \PDO::PARAM_STR,
        ];
        if (method_exists($this->conn, 'executeStatement')) {
            $updated = $this->conn->executeStatement($sql, $paramValues, $paramTypes);
        } else {
            $updated = $this->conn->executeUpdate($sql, $paramValues, $paramTypes);
        }
        if ($updated < 1) {
            throw new TokenNotFoundException('No token found.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createNewToken(PersistentTokenInterface $token)
    {
        $sql = 'INSERT INTO rememberme_token'
            .' (class, username, series, value, lastUsed)'
            .' VALUES (:class, :username, :series, :value, :lastUsed)';
        $paramValues = [
            'class' => $token->getClass(),
            'username' => $token->getUsername(),
            'series' => $token->getSeries(),
            'value' => $token->getTokenValue(),
            'lastUsed' => $token->getLastUsed(),
        ];
        $paramTypes = [
            'class' => \PDO::PARAM_STR,
            'username' => \PDO::PARAM_STR,
            'series' => \PDO::PARAM_STR,
            'value' => \PDO::PARAM_STR,
            'lastUsed' => self::$useDeprecatedConstants ? Type::DATETIME : Types::DATETIME_MUTABLE,
        ];
        if (method_exists($this->conn, 'executeStatement')) {
            $this->conn->executeStatement($sql, $paramValues, $paramTypes);
        } else {
            $this->conn->executeUpdate($sql, $paramValues, $paramTypes);
        }
    }
}
