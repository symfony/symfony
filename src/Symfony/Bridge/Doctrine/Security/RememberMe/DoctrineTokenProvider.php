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
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentTokenInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenVerifierInterface;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

/**
 * This class provides storage for the tokens that is set in "remember-me"
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
 *
 * @final since Symfony 6.4
 */
class DoctrineTokenProvider implements TokenProviderInterface, TokenVerifierInterface
{
    public function __construct(
        private readonly Connection $conn,
    ) {
    }

    public function loadTokenBySeries(string $series): PersistentTokenInterface
    {
        $sql = 'SELECT class, username, value, lastUsed FROM rememberme_token WHERE series=:series';
        $paramValues = ['series' => $series];
        $paramTypes = ['series' => ParameterType::STRING];
        $stmt = $this->conn->executeQuery($sql, $paramValues, $paramTypes);

        // fetching numeric because column name casing depends on platform, eg. Oracle converts all not quoted names to uppercase
        $row = $stmt instanceof Result || $stmt instanceof DriverResult ? $stmt->fetchNumeric() : $stmt->fetch(\PDO::FETCH_NUM);

        if ($row) {
            [$class, $username, $value, $last_used] = $row;
            return new PersistentToken($class, $username, $series, $value, new \DateTimeImmutable($last_used));
        }

        throw new TokenNotFoundException('No token found.');
    }

    /**
     * @return void
     */
    public function deleteTokenBySeries(string $series)
    {
        $sql = 'DELETE FROM rememberme_token WHERE series=:series';
        $paramValues = ['series' => $series];
        $paramTypes = ['series' => ParameterType::STRING];
        $this->conn->executeStatement($sql, $paramValues, $paramTypes);
    }

    public function updateToken(string $series, #[\SensitiveParameter] string $tokenValue, \DateTimeInterface $lastUsed): void
    {
        $sql = 'UPDATE rememberme_token SET value=:value, lastUsed=:lastUsed WHERE series=:series';
        $paramValues = [
            'value' => $tokenValue,
            'lastUsed' => \DateTimeImmutable::createFromInterface($lastUsed),
            'series' => $series,
        ];
        $paramTypes = [
            'value' => ParameterType::STRING,
            'lastUsed' => Types::DATETIME_IMMUTABLE,
            'series' => ParameterType::STRING,
        ];
        $updated = $this->conn->executeStatement($sql, $paramValues, $paramTypes);
        if ($updated < 1) {
            throw new TokenNotFoundException('No token found.');
        }
    }

    /**
     * @return void
     */
    public function createNewToken(PersistentTokenInterface $token)
    {
        $sql = 'INSERT INTO rememberme_token (class, username, series, value, lastUsed) VALUES (:class, :username, :series, :value, :lastUsed)';
        $paramValues = [
            'class' => $token->getClass(),
            'username' => $token->getUserIdentifier(),
            'series' => $token->getSeries(),
            'value' => $token->getTokenValue(),
            'lastUsed' => \DateTimeImmutable::createFromInterface($token->getLastUsed()),
        ];
        $paramTypes = [
            'class' => ParameterType::STRING,
            'username' => ParameterType::STRING,
            'series' => ParameterType::STRING,
            'value' => ParameterType::STRING,
            'lastUsed' => Types::DATETIME_IMMUTABLE,
        ];
        $this->conn->executeStatement($sql, $paramValues, $paramTypes);
    }

    public function verifyToken(PersistentTokenInterface $token, #[\SensitiveParameter] string $tokenValue): bool
    {
        // Check if the token value matches the current persisted token
        if (hash_equals($token->getTokenValue(), $tokenValue)) {
            return true;
        }

        // Generate an alternative series id here by changing the suffix == to _
        // this is needed to be able to store an older token value in the database
        // which has a PRIMARY(series), and it works as long as series ids are
        // generated using base64_encode(random_bytes(64)) which always outputs
        // a == suffix, but if it should not work for some reason we abort
        // for safety
        $tmpSeries = preg_replace('{=+$}', '_', $token->getSeries());
        if ($tmpSeries === $token->getSeries()) {
            return false;
        }

        // Check if the previous token is present. If the given $tokenValue
        // matches the previous token (and it is outdated by at most 60seconds)
        // we also accept it as a valid value.
        try {
            $tmpToken = $this->loadTokenBySeries($tmpSeries);
        } catch (TokenNotFoundException) {
            return false;
        }

        if ($tmpToken->getLastUsed()->getTimestamp() + 60 < time()) {
            return false;
        }

        return hash_equals($tmpToken->getTokenValue(), $tokenValue);
    }

    public function updateExistingToken(PersistentTokenInterface $token, #[\SensitiveParameter] string $tokenValue, \DateTimeInterface $lastUsed): void
    {
        if (!$token instanceof PersistentToken) {
            return;
        }

        // Persist a copy of the previous token for authentication
        // in verifyToken should the old token still be sent by the browser
        // in a request concurrent to the one that did this token update
        $tmpSeries = preg_replace('{=+$}', '_', $token->getSeries());
        // if we cannot generate a unique series it is not worth trying further
        if ($tmpSeries === $token->getSeries()) {
            return;
        }

        $this->conn->beginTransaction();
        try {
            $this->deleteTokenBySeries($tmpSeries);
            $lastUsed = \DateTime::createFromInterface($lastUsed);
            $this->createNewToken(new PersistentToken($token->getClass(), $token->getUserIdentifier(), $tmpSeries, $token->getTokenValue(), $lastUsed));

            $this->conn->commit();
        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Adds the Table to the Schema if "remember me" uses this Connection.
     *
     * @param \Closure $isSameDatabase
     */
    public function configureSchema(Schema $schema, Connection $forConnection/* , \Closure $isSameDatabase */): void
    {
        if ($schema->hasTable('rememberme_token')) {
            return;
        }

        $isSameDatabase = 2 < \func_num_args() ? func_get_arg(2) : static fn () => false;

        if ($forConnection !== $this->conn && !$isSameDatabase($this->conn->executeStatement(...))) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable('rememberme_token');
        $table->addColumn('series', Types::STRING, ['length' => 88]);
        $table->addColumn('value', Types::STRING, ['length' => 88]);
        $table->addColumn('lastUsed', Types::DATETIME_IMMUTABLE);
        $table->addColumn('class', Types::STRING, ['length' => 100]);
        $table->addColumn('username', Types::STRING, ['length' => 200]);
        $table->setPrimaryKey(['series']);
    }
}
