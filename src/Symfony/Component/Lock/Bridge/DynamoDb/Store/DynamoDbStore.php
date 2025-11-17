<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Bridge\DynamoDb\Store;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException;
use AsyncAws\DynamoDb\Exception\ResourceNotFoundException;
use AsyncAws\DynamoDb\Input\CreateTableInput;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\DescribeTableInput;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeDefinition;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use AsyncAws\DynamoDb\ValueObject\KeySchemaElement;
use AsyncAws\DynamoDb\ValueObject\ProvisionedThroughput;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\ExpiringStoreTrait;

class DynamoDbStore implements PersistingStoreInterface
{
    use ExpiringStoreTrait;

    private const DEFAULT_OPTIONS = [
        'session_token' => null,
        'endpoint' => null,
        'region' => null,
        'table_name' => 'lock_keys',
        'id_attr' => 'key_id',
        'token_attr' => 'key_token',
        'expiration_attr' => 'key_expiration',
        'read_capacity_units' => 10,
        'write_capacity_units' => 20,
        'sslmode' => null,
        'debug' => null,
        'http_client' => null,
    ];

    private DynamoDbClient $client;
    private string $tableName;
    private string $idAttr;
    private string $tokenAttr;
    private string $expirationAttr;
    private int $readCapacityUnits;
    private int $writeCapacityUnits;

    public function __construct(
        #[\SensitiveParameter] DynamoDbClient|string $clientOrDsn,
        array $options = [],
        private readonly int $initialTtl = 300,
    ) {
        if ($clientOrDsn instanceof DynamoDbClient) {
            $this->client = $clientOrDsn;
        } else {
            if (!str_starts_with($clientOrDsn, 'dynamodb:')) {
                throw new InvalidArgumentException('Unsupported DSN for DynamoDB.');
            }

            if (false === $params = parse_url($clientOrDsn)) {
                throw new InvalidArgumentException('The given Amazon DynamoDB DSN is invalid.');
            }

            $query = [];
            if (isset($params['query'])) {
                parse_str($params['query'], $query);
            }

            // check for extra keys in options
            $optionsExtraKeys = array_diff_key($options, self::DEFAULT_OPTIONS);
            if (0 < \count($optionsExtraKeys)) {
                throw new InvalidArgumentException(\sprintf('Unknown option found: [%s]. Allowed options are [%s].', implode(', ', $optionsExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
            }

            // check for extra keys in query
            $queryExtraKeys = array_diff_key($query, self::DEFAULT_OPTIONS);
            if (0 < \count($queryExtraKeys)) {
                throw new InvalidArgumentException(\sprintf('Unknown option found in DSN: [%s]. Allowed options are [%s].', implode(', ', $queryExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
            }

            $options = $query + $options + self::DEFAULT_OPTIONS;

            $clientConfiguration = [
                'region' => $options['region'],
                'accessKeyId' => rawurldecode($params['user'] ?? '') ?: null,
                'accessKeySecret' => rawurldecode($params['pass'] ?? '') ?: null,
            ];
            if (null !== $options['session_token']) {
                $clientConfiguration['sessionToken'] = $options['session_token'];
            }
            if (isset($options['debug'])) {
                $clientConfiguration['debug'] = $options['debug'];
            }
            unset($query['region']);

            if ('default' !== ($params['host'] ?? 'default')) {
                $clientConfiguration['endpoint'] = \sprintf('%s://%s%s', ($options['sslmode'] ?? null) === 'disable' ? 'http' : 'https', $params['host'], ($params['port'] ?? null) ? ':'.$params['port'] : '');
                if (preg_match(';^dynamodb\.([^\.]++)\.amazonaws\.com$;', $params['host'], $matches)) {
                    $clientConfiguration['region'] = $matches[1];
                }
            } elseif (null !== ($options['endpoint'] ?? self::DEFAULT_OPTIONS['endpoint'])) {
                $clientConfiguration['endpoint'] = $options['endpoint'];
            }

            $parsedPath = explode('/', ltrim($params['path'] ?? '/', '/'));
            if ($tableName = end($parsedPath)) {
                $options['table_name'] = $tableName;
            }

            $this->client = new DynamoDbClient($clientConfiguration, null, $options['http_client']);
        }

        $this->tableName = $options['table_name'] ?? self::DEFAULT_OPTIONS['table_name'];
        $this->idAttr = $options['id_attr'] ?? self::DEFAULT_OPTIONS['id_attr'];
        $this->tokenAttr = $options['token_attr'] ?? self::DEFAULT_OPTIONS['token_attr'];
        $this->expirationAttr = $options['expiration_attr'] ?? self::DEFAULT_OPTIONS['expiration_attr'];
        $this->readCapacityUnits = $options['read_capacity_units'] ?? self::DEFAULT_OPTIONS['read_capacity_units'];
        $this->writeCapacityUnits = $options['write_capacity_units'] ?? self::DEFAULT_OPTIONS['write_capacity_units'];
    }

    public function save(Key $key): void
    {
        $key->reduceLifetime($this->initialTtl);

        $input = new PutItemInput([
            'TableName' => $this->tableName,
            'Item' => [
                $this->idAttr => new AttributeValue(['S' => $this->getHashedKey($key)]),
                $this->tokenAttr => new AttributeValue(['S' => $this->getUniqueToken($key)]),
                $this->expirationAttr => new AttributeValue(['N' => (string) (microtime(true) + $this->initialTtl)]),
            ],
            'ConditionExpression' => 'attribute_not_exists(#key) OR #expires_at < :now',
            'ExpressionAttributeNames' => [
                '#key' => $this->idAttr,
                '#expires_at' => $this->expirationAttr,
            ],
            'ExpressionAttributeValues' => [
                ':now' => new AttributeValue(['N' => (string) microtime(true)]),
            ],
        ]);

        try {
            $this->client->putItem($input);
        } catch (ResourceNotFoundException) {
            $this->createTable();

            try {
                $this->client->putItem($input);
            } catch (ConditionalCheckFailedException) {
                $this->putOffExpiration($key, $this->initialTtl);
            }
        } catch (ConditionalCheckFailedException) {
            // the lock is already acquired. It could be us. Let's try to put off.
            $this->putOffExpiration($key, $this->initialTtl);
        } catch (\Throwable $throwable) {
            throw new LockAcquiringException('Failed to acquire lock.', 0, $throwable);
        }

        $this->checkNotExpired($key);
    }

    public function delete(Key $key): void
    {
        $this->client->deleteItem(new DeleteItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                $this->idAttr => new AttributeValue(['S' => $this->getHashedKey($key)]),
            ],
        ]));
    }

    public function exists(Key $key): bool
    {
        $existingLock = $this->client->getItem(new GetItemInput([
            'TableName' => $this->tableName,
            'ConsistentRead' => true,
            'Key' => [
                $this->idAttr => new AttributeValue(['S' => $this->getHashedKey($key)]),
            ],
        ]));

        $item = $existingLock->getItem();

        // Item not found at all
        if (!$item) {
            return false;
        }

        // We are not the owner
        if (!isset($item[$this->tokenAttr]) || $this->getUniqueToken($key) !== $item[$this->tokenAttr]->getS()) {
            return false;
        }

        // If item is expired, consider it doesn't exist
        return isset($item[$this->expirationAttr]) && ((float) $item[$this->expirationAttr]->getN()) > microtime(true);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        if ($ttl < 1) {
            throw new InvalidTtlException(\sprintf('"%s()" expects a TTL greater or equals to 1 second. Got "%s".', __METHOD__, $ttl));
        }

        $key->reduceLifetime($ttl);

        $uniqueToken = $this->getUniqueToken($key);

        try {
            $this->client->putItem(new PutItemInput([
                'TableName' => $this->tableName,
                'Item' => [
                    $this->idAttr => new AttributeValue(['S' => $this->getHashedKey($key)]),
                    $this->tokenAttr => new AttributeValue(['S' => $uniqueToken]),
                    $this->expirationAttr => new AttributeValue(['N' => (string) (microtime(true) + $ttl)]),
                ],
                'ConditionExpression' => 'attribute_exists(#key) AND (#token = :token OR #expires_at <= :now)',
                'ExpressionAttributeNames' => [
                    '#key' => $this->idAttr,
                    '#expires_at' => $this->expirationAttr,
                    '#token' => $this->tokenAttr,
                ],
                'ExpressionAttributeValues' => [
                    ':now' => new AttributeValue(['N' => (string) microtime(true)]),
                    ':token' => new AttributeValue(['S' => $uniqueToken]),
                ],
            ]));
        } catch (ConditionalCheckFailedException) {
            // The item doesn't exist or was acquired by someone else
            throw new LockConflictedException();
        } catch (\Throwable $throwable) {
            throw new LockAcquiringException('Failed to acquire lock.', 0, $throwable);
        }

        $this->checkNotExpired($key);
    }

    public function createTable(): void
    {
        $this->client->createTable(new CreateTableInput([
            'TableName' => $this->tableName,
            'AttributeDefinitions' => [
                new AttributeDefinition(['AttributeName' => $this->idAttr, 'AttributeType' => 'S']),
            ],
            'KeySchema' => [
                new KeySchemaElement(['AttributeName' => $this->idAttr, 'KeyType' => 'HASH']),
            ],
            'ProvisionedThroughput' => new ProvisionedThroughput([
                'ReadCapacityUnits' => $this->readCapacityUnits,
                'WriteCapacityUnits' => $this->writeCapacityUnits,
            ]),
        ]));

        $this->client->tableExists(new DescribeTableInput(['TableName' => $this->tableName]))->wait();
    }

    private function getHashedKey(Key $key): string
    {
        return hash('sha256', (string) $key);
    }

    private function getUniqueToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }
}
