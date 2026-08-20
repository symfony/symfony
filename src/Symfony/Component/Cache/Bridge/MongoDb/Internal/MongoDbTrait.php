<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Bridge\MongoDb\Internal;

use MongoDB\BSON\Binary;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Psr\Clock\ClockInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

/**
 * Code shared by the MongoDB cache adapters.
 *
 * All I/O goes through the mongodb/mongodb library so that the read/write
 * concerns and the read preference configured on the connection string or on
 * the injected collection are inherited, unless they are set in the options.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * @internal
 */
trait MongoDbTrait
{
    private Collection $collection;
    private MarshallerInterface $marshaller;
    private ?ClockInterface $clock;

    /**
     * The adapter options that are read from the DSN query string and must not
     * be forwarded to the driver.
     */
    private const DSN_OPTIONS = [
        'namespace' => '',
        'database_name' => null,
        'collection_name' => null,
    ];

    private function init(Collection|Client|Database|string $mongo, string $namespace, int $defaultLifetime, array $options, MarshallerInterface $marshaller, ?ClockInterface $clock): void
    {
        if (\is_string($mongo)) {
            [$options, $mongo] = self::parseDsn($mongo, $options);
        }

        $options += self::DSN_OPTIONS + ['driverOptions' => []];

        // The positional namespace wins; fall back to the DSN "namespace" query parameter.
        parent::__construct($namespace ?: $options['namespace'], $defaultLifetime);

        // Every option that is not consumed by the adapter is forwarded to the
        // collection (read/write concerns, read preference). The raw BSON type
        // map and the disabled codec are then forced, since the adapter reads
        // and writes plain documents itself.
        $collectionOptions = array_diff_key($options, self::DSN_OPTIONS + ['driverOptions' => null]);
        $collectionOptions['typeMap'] = ['root' => 'bson'];
        $collectionOptions['codec'] = null;

        if ($mongo instanceof Collection) {
            $this->collection = $mongo->withOptions($collectionOptions);
        } elseif ($mongo instanceof Database) {
            if (null === $options['collection_name']) {
                throw new InvalidArgumentException('The "collection_name" option is required when passing a "MongoDB\Database" instance.');
            }
            $this->collection = $mongo->getCollection($options['collection_name'], $collectionOptions);
        } elseif ($mongo instanceof Client) {
            if (null === $options['database_name'] || null === $options['collection_name']) {
                throw new InvalidArgumentException('The "database_name" and "collection_name" options are required when passing a "MongoDB\Client" instance.');
            }
            $this->collection = $mongo->getCollection($options['database_name'], $options['collection_name'], $collectionOptions);
        } else {
            // $mongo is the connection string stripped from the adapter options by parseDsn().
            // Connection options are read from the DSN query string.
            $client = new Client($mongo, [], $options['driverOptions']);
            $this->collection = $client->getCollection($options['database_name'], $options['collection_name'], $collectionOptions);
        }

        $this->marshaller = $marshaller;
        $this->clock = $clock;
        $this->maxIdLength = 1024;
    }

    /**
     * Builds a MongoDB collection from a DSN, ready to back a cache adapter.
     *
     * This is the entry point used by AbstractAdapter::createConnection() so
     * that a "mongodb:" cache DSN produces a connection service.
     */
    public static function createConnection(#[\SensitiveParameter] string $dsn, array $options = []): Collection
    {
        [$options, $uri] = self::parseDsn($dsn, $options);

        $driverOptions = $options['driverOptions'] ?? [];

        // Any remaining option is forwarded as a client URI option (for example
        // "readPreference", "readConcernLevel" or "w"), on top of what the DSN
        // already carries. The adapter and cache-pool specific keys are dropped,
        // including the "lazy" flag (the client only connects on first use). The
        // raw BSON type map is forced, since the adapter reads and writes plain
        // documents itself.
        $uriOptions = array_diff_key($options, self::DSN_OPTIONS + ['driverOptions' => null, 'lazy' => null]);
        $uriOptions['typeMap'] = ['root' => 'bson'];

        $client = new Client($uri, $uriOptions, $driverOptions);

        return $client->getCollection($options['database_name'], $options['collection_name']);
    }

    /**
     * Creates a TTL index on the "expires_at" field so that the server removes
     * expired entries by itself. Call it once during setup.
     */
    public function setup(): void
    {
        // The index is partial so that entries stored without an expiration
        // (a zero lifetime) are not indexed at all.
        $this->collection->createIndex(
            ['expires_at' => 1],
            ['expireAfterSeconds' => 0, 'partialFilterExpression' => ['expires_at' => ['$exists' => true]]],
        );
    }

    public function prune(): bool
    {
        // Deleting every expired document (the same criterion a TTL index uses)
        // relies on the "expires_at" index instead of a key-prefix scan.
        $this->collection->deleteMany(['expires_at' => ['$lte' => $this->createUtcDateTime()]]);

        return true;
    }

    protected function doFetch(array $ids): iterable
    {
        $cursor = $this->collection->find(
            [
                '_id' => ['$in' => array_values($ids)],
                '$or' => [
                    ['expires_at' => ['$exists' => false]],
                    ['expires_at' => ['$gt' => $this->createUtcDateTime()]],
                ],
            ],
            ['projection' => ['_id' => 1, 'data' => 1]]
        );

        /** @var \MongoDB\BSON\Document $document */
        foreach ($cursor as $document) {
            yield (string) $document['_id'] => $this->marshaller->unmarshall($document['data']->getData());
        }
    }

    protected function doHave(string $id): bool
    {
        return 0 < $this->collection->countDocuments(
            [
                '_id' => ['$eq' => $id],
                '$or' => [
                    ['expires_at' => ['$exists' => false]],
                    ['expires_at' => ['$gt' => $this->createUtcDateTime()]],
                ],
            ],
            ['limit' => 1]
        );
    }

    protected function doClear(string $namespace): bool
    {
        if ('' === $namespace) {
            $this->collection->deleteMany([]);
        } else {
            $this->collection->deleteMany(['_id' => new Regex('^'.preg_quote($namespace))]);
        }

        return true;
    }

    protected function doDelete(array $ids): bool
    {
        $this->collection->deleteMany(['_id' => ['$in' => array_values($ids)]]);

        return true;
    }

    /**
     * Reads the adapter options from the DSN and returns them along with the
     * connection string stripped from those options, ready for MongoDB\Client.
     *
     * The database is read from the DSN path, the collection from the
     * "collection_name" query parameter and the key prefix from the
     * "namespace" query parameter. Explicit options take precedence. Any
     * other query parameter is kept and passed to the driver.
     *
     * @return array{0: array, 1: string}
     */
    private static function parseDsn(#[\SensitiveParameter] string $dsn, array $options): array
    {
        if (!str_starts_with($dsn, 'mongodb://') && !str_starts_with($dsn, 'mongodb+srv://')) {
            throw new InvalidArgumentException('The given MongoDB DSN is invalid. Expecting "mongodb://" or "mongodb+srv://".');
        }

        if (false === $params = parse_url($dsn)) {
            throw new InvalidArgumentException('The given MongoDB DSN is invalid.');
        }

        $query = [];
        if (isset($params['query'])) {
            parse_str($params['query'], $query);
        }

        $options['database_name'] ??= $query['database_name'] ?? (ltrim($params['path'] ?? '', '/') ?: null);
        $options['collection_name'] ??= $query['collection_name'] ?? null;
        $options['namespace'] ??= $query['namespace'] ?? '';

        if (null === $options['database_name']) {
            throw new InvalidArgumentException('The MongoDB cache adapter requires a "database_name", provide it in the DSN path or as an option.');
        }
        if (null === $options['collection_name']) {
            throw new InvalidArgumentException('The MongoDB cache adapter requires a "collection_name", provide it in the DSN "collection_name" query parameter or as an option.');
        }

        return [$options, self::stripDsnOptions($dsn)];
    }

    /**
     * Removes the adapter's query parameters from the DSN in a single pass,
     * keeping the other parameters (including repeated driver parameters such
     * as "readPreferenceTags") and fixing the query separators.
     */
    private static function stripDsnOptions(#[\SensitiveParameter] string $dsn): string
    {
        $first = true;

        return preg_replace_callback(
            '/[?&]([^=&#]++)=[^&#]*+/',
            static function (array $matches) use (&$first): string {
                if (\array_key_exists($matches[1], self::DSN_OPTIONS)) {
                    return '';
                }

                $separator = $first ? '?' : '&';
                $first = false;

                return $separator.substr($matches[0], 1);
            },
            $dsn,
        );
    }

    private function now(): float
    {
        if (null !== $this->clock) {
            return (float) $this->clock->now()->format('U.u');
        }

        return microtime(true);
    }

    private function createUtcDateTime(?float $seconds = null): UTCDateTime
    {
        return new UTCDateTime((int) (($seconds ?? $this->now()) * 1000));
    }

    /**
     * The absolute expiry date for the given lifetime, or null to persist until
     * manual cleaning.
     */
    private function expiry(int $lifetime): ?UTCDateTime
    {
        return 0 < $lifetime ? $this->createUtcDateTime($this->now() + $lifetime) : null;
    }

    private static function createBinary(string $data): Binary
    {
        return new Binary($data, Binary::TYPE_GENERIC);
    }
}
