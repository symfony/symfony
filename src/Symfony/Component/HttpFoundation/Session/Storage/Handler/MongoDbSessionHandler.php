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

use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

/**
 * Session handler using the MongoDB driver extension.
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * @see https://php.net/mongodb
 */
class MongoDbSessionHandler extends AbstractSessionHandler implements ClearableSessionHandlerInterface
{
    /**
     * The handler options read from the DSN query string, with their default
     * value. They are removed from the DSN before passing it to the driver, so
     * that the driver reuses the same connection for a given MONGODB_URI.
     */
    private const DEFAULT_OPTIONS = [
        'database' => 'app',
        'collection' => 'sessions',
        'ttl' => null,
        'id_field' => '_id',
        'data_field' => 'data',
        'time_field' => 'time',
        'expiry_field' => 'expires_at',
    ];

    private Manager $manager;
    private string $namespace;
    private array $options;
    private int|\Closure|null $ttl;

    /**
     * Creates a handler from a "mongodb://" or "mongodb+srv://" DSN.
     *
     * The database is read from the DSN path or the "database" query parameter
     * [default: app], the collection from the "collection" query parameter
     * [default: sessions]. The other query parameters are forwarded to the
     * MongoDB driver. Explicit options take precedence over the DSN.
     */
    public static function createFromDsn(#[\SensitiveParameter] string $dsn, array $options = []): self
    {
        if (!class_exists(Manager::class)) {
            throw new \InvalidArgumentException('Unsupported MongoDB DSN. The "mongodb" PHP extension is required.');
        }

        if (!str_starts_with($dsn, 'mongodb://') && !str_starts_with($dsn, 'mongodb+srv://')) {
            throw new \InvalidArgumentException('The given MongoDB DSN is invalid. Expecting "mongodb://" or "mongodb+srv://".');
        }

        [$uri, $query, $path] = self::parseDsn($dsn);

        if (isset($options['database']) && '' === $options['database']) {
            throw new \InvalidArgumentException('The "database" option must not be empty.');
        }
        if (isset($options['collection']) && '' === $options['collection']) {
            throw new \InvalidArgumentException('The "collection" option must not be empty.');
        }

        $database = rawurldecode(ltrim($path, '/'));
        $options['database'] ??= '' !== $database ? $database : ($query['database'] ?? self::DEFAULT_OPTIONS['database']);
        unset($query['database']);
        $options += array_intersect_key($query, self::DEFAULT_OPTIONS) + self::DEFAULT_OPTIONS;

        if (isset($options['ttl']) && !$options['ttl'] instanceof \Closure) {
            if (!is_numeric($options['ttl'])) {
                throw new \InvalidArgumentException('The "ttl" option must be an integer or a Closure.');
            }
            $options['ttl'] = (int) $options['ttl'];
        }

        return new self(new Manager($uri), array_intersect_key($options, self::DEFAULT_OPTIONS));
    }

    /**
     * Constructor.
     *
     * List of available options:
     *  * database: The name of the database [required]
     *  * collection: The name of the collection [required]
     *  * id_field: The field name for storing the session id [default: _id]
     *  * data_field: The field name for storing the session data [default: data]
     *  * time_field: The field name for storing the timestamp [default: time]
     *  * expiry_field: The field name for storing the expiry-timestamp [default: expires_at]
     *  * ttl: The time to live in seconds.
     *
     * It is strongly recommended to put an index on the `expiry_field` for
     * garbage-collection. Alternatively it's possible to automatically expire
     * the sessions in the database as described below:
     *
     * A TTL collections can be used on MongoDB 2.2+ to cleanup expired sessions
     * automatically. Such an index can for example look like this:
     *
     *     db.<session-collection>.createIndex(
     *         { "<expiry-field>": 1 },
     *         { "expireAfterSeconds": 0 }
     *     )
     *
     * More details on: https://docs.mongodb.org/manual/tutorial/expire-data/
     *
     * If you use such an index, you can drop `gc_probability` to 0 since
     * no garbage-collection is required.
     *
     * @throws \InvalidArgumentException When "database" or "collection" not provided
     */
    public function __construct(Client|Manager $mongo, array $options)
    {
        if (!isset($options['database']) || !isset($options['collection'])) {
            throw new \InvalidArgumentException('You must provide the "database" and "collection" option for MongoDBSessionHandler.');
        }

        if ($mongo instanceof Client) {
            $mongo = $mongo->getManager();
        }

        $this->manager = $mongo;
        $this->namespace = $options['database'].'.'.$options['collection'];

        $this->options = array_merge([
            'id_field' => '_id',
            'data_field' => 'data',
            'time_field' => 'time',
            'expiry_field' => 'expires_at',
        ], $options);
        $this->ttl = $this->options['ttl'] ?? null;
    }

    public function close(): bool
    {
        return true;
    }

    protected function doDestroy(#[\SensitiveParameter] string $sessionId): bool
    {
        $write = new BulkWrite();
        $write->delete(
            [$this->options['id_field'] => $sessionId],
            ['limit' => 1]
        );

        $this->manager->executeBulkWrite($this->namespace, $write);

        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        $write = new BulkWrite();
        $write->delete(
            [$this->options['expiry_field'] => ['$lt' => $this->getUTCDateTime()]],
        );
        $result = $this->manager->executeBulkWrite($this->namespace, $write);

        return $result->getDeletedCount() ?? false;
    }

    protected function doWrite(#[\SensitiveParameter] string $sessionId, string $data): bool
    {
        $ttl = ($this->ttl instanceof \Closure ? ($this->ttl)() : $this->ttl) ?? \ini_get('session.gc_maxlifetime');
        $expiry = $this->getUTCDateTime($ttl);

        $fields = [
            $this->options['time_field'] => $this->getUTCDateTime(),
            $this->options['expiry_field'] => $expiry,
            $this->options['data_field'] => new Binary($data, Binary::TYPE_GENERIC),
        ];

        $write = new BulkWrite();
        $write->update(
            [$this->options['id_field'] => $sessionId],
            ['$set' => $fields],
            ['upsert' => true]
        );

        $this->manager->executeBulkWrite($this->namespace, $write);

        return true;
    }

    public function updateTimestamp(#[\SensitiveParameter] string $sessionId, string $data): bool
    {
        // PHP 8.6 calls updateTimestamp() instead of write() for a session that is still empty
        if ('' === $data || (\function_exists('igbinary_serialize') && igbinary_serialize([]) === $data)) {
            return $this->destroy($sessionId);
        }

        $ttl = ($this->ttl instanceof \Closure ? ($this->ttl)() : $this->ttl) ?? \ini_get('session.gc_maxlifetime');
        $expiry = $this->getUTCDateTime($ttl);

        $write = new BulkWrite();
        $write->update(
            [$this->options['id_field'] => $sessionId],
            ['$set' => [
                $this->options['time_field'] => $this->getUTCDateTime(),
                $this->options['expiry_field'] => $expiry,
            ]],
            ['multi' => false],
        );

        $this->manager->executeBulkWrite($this->namespace, $write);

        return true;
    }

    protected function doRead(#[\SensitiveParameter] string $sessionId): string
    {
        $cursor = $this->manager->executeQuery($this->namespace, new Query([
            $this->options['id_field'] => $sessionId,
            $this->options['expiry_field'] => ['$gte' => $this->getUTCDateTime()],
        ], [
            'projection' => [
                '_id' => false,
                $this->options['data_field'] => true,
            ],
            'limit' => 1,
        ]));

        foreach ($cursor as $document) {
            return (string) $document->{$this->options['data_field']} ?? '';
        }

        // Not found
        return '';
    }

    public function clear(): void
    {
        $write = new BulkWrite();
        $write->delete([]);
        $this->manager->executeBulkWrite($this->namespace, $write);
    }

    /**
     * Removes the handler options from the DSN and returns the remaining URI,
     * the query options and the path.
     *
     * URI options are kept in order, including repeated ones such as
     * "readPreferenceTags".
     *
     * @return array{0: string, 1: array<string, string>, 2: string}
     */
    private static function parseDsn(#[\SensitiveParameter] string $dsn): array
    {
        if (false === $params = parse_url($dsn)) {
            throw new \InvalidArgumentException('The given MongoDB DSN is invalid.');
        }

        if ('' === ($params['host'] ?? '')) {
            throw new \InvalidArgumentException('The given MongoDB DSN is invalid: no host specified.');
        }

        $query = $kept = [];
        foreach (explode('&', $params['query'] ?? '') as $pair) {
            if ('' === $pair) {
                continue;
            }

            $eq = strpos($pair, '=');
            $key = rawurldecode(false === $eq ? $pair : substr($pair, 0, $eq));
            $value = false === $eq ? '' : rawurldecode(substr($pair, $eq + 1));

            if (('database' === $key || 'collection' === $key) && '' === $value) {
                throw new \InvalidArgumentException(\sprintf('The "%s" DSN option must not be empty.', $key));
            }

            $query[$key] = $value;
            if (!\array_key_exists($key, self::DEFAULT_OPTIONS)) {
                $kept[] = $pair;
            }
        }

        $uri = $dsn;
        if (isset($params['query'])) {
            $uri = str_replace('?'.$params['query'], $kept ? '?'.implode('&', $kept) : '', $uri);
        }

        return [$uri, $query, $params['path'] ?? ''];
    }

    private function getUTCDateTime(int $additionalSeconds = 0): UTCDateTime
    {
        return new UTCDateTime((time() + $additionalSeconds) * 1000);
    }
}
