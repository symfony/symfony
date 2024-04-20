<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

trigger_deprecation('symfony/cache', '7.1', 'The "%s" class is deprecated, use "%s" instead.', CouchbaseBucketAdapter::class, CouchbaseCollectionAdapter::class);

/**
 * @author Antonio Jose Cerezo Aranda <aj.cerezo@gmail.com>
 *
 * @deprecated since Symfony 7.1, use {@see CouchbaseCollectionAdapter} instead
 */
class CouchbaseBucketAdapter extends AbstractAdapter
{
    private const THIRTY_DAYS_IN_SECONDS = 2592000;
    private const MAX_KEY_LENGTH = 250;
    private const KEY_NOT_FOUND = 13;
    private const VALID_DSN_OPTIONS = [
        'operationTimeout',
        'configTimeout',
        'configNodeTimeout',
        'n1qlTimeout',
        'httpTimeout',
        'configDelay',
        'htconfigIdleTimeout',
        'durabilityInterval',
        'durabilityTimeout',
    ];

    private MarshallerInterface $marshaller;

    public function __construct(
        private \CouchbaseBucket $bucket,
        string $namespace = '',
        int $defaultLifetime = 0,
        ?MarshallerInterface $marshaller = null,
    ) {
        if (!static::isSupported()) {
            throw new CacheException('Couchbase >= 2.6.0 < 3.0.0 is required.');
        }

        $this->maxIdLength = static::MAX_KEY_LENGTH;

        parent::__construct($namespace, $defaultLifetime);
        $this->enableVersioning();
        $this->marshaller = $marshaller ?? new DefaultMarshaller();
    }

    public static function createConnection(#[\SensitiveParameter] array|string $servers, array $options = []): \CouchbaseBucket
    {
        if (\is_string($servers)) {
            $servers = [$servers];
        }

        if (!static::isSupported()) {
            throw new CacheException('Couchbase >= 2.6.0 < 3.0.0 is required.');
        }

        set_error_handler(static fn ($type, $msg, $file, $line) => throw new \ErrorException($msg, 0, $type, $file, $line));

        $dsnPattern = '/^(?<protocol>couchbase(?:s)?)\:\/\/(?:(?<username>[^\:]+)\:(?<password>[^\@]{6,})@)?'
            .'(?<host>[^\:]+(?:\:\d+)?)(?:\/(?<bucketName>[^\?]+))(?:\?(?<options>.*))?$/i';

        $newServers = [];
        $protocol = 'couchbase';
        try {
            $options = self::initOptions($options);
            $username = $options['username'];
            $password = $options['password'];

            foreach ($servers as $dsn) {
                if (!str_starts_with($dsn, 'couchbase:')) {
                    throw new InvalidArgumentException('Invalid Couchbase DSN: it does not start with "couchbase:".');
                }

                preg_match($dsnPattern, $dsn, $matches);

                $username = $matches['username'] ?: $username;
                $password = $matches['password'] ?: $password;
                $protocol = $matches['protocol'] ?: $protocol;

                if (isset($matches['options'])) {
                    $optionsInDsn = self::getOptions($matches['options']);

                    foreach ($optionsInDsn as $parameter => $value) {
                        $options[$parameter] = $value;
                    }
                }

                $newServers[] = $matches['host'];
            }

            $connectionString = $protocol.'://'.implode(',', $newServers);

            $client = new \CouchbaseCluster($connectionString);
            $client->authenticateAs($username, $password);

            $bucket = $client->openBucket($matches['bucketName']);

            unset($options['username'], $options['password']);
            foreach ($options as $option => $value) {
                if ($value) {
                    $bucket->$option = $value;
                }
            }

            return $bucket;
        } finally {
            restore_error_handler();
        }
    }

    public static function isSupported(): bool
    {
        return \extension_loaded('couchbase') && version_compare(phpversion('couchbase'), '2.6.0', '>=') && version_compare(phpversion('couchbase'), '3.0', '<');
    }

    private static function getOptions(string $options): array
    {
        $results = [];
        $optionsInArray = explode('&', $options);

        foreach ($optionsInArray as $option) {
            [$key, $value] = explode('=', $option);

            if (\in_array($key, static::VALID_DSN_OPTIONS, true)) {
                $results[$key] = $value;
            }
        }

        return $results;
    }

    private static function initOptions(array $options): array
    {
        $options['username'] ??= '';
        $options['password'] ??= '';
        $options['operationTimeout'] ??= 0;
        $options['configTimeout'] ??= 0;
        $options['configNodeTimeout'] ??= 0;
        $options['n1qlTimeout'] ??= 0;
        $options['httpTimeout'] ??= 0;
        $options['configDelay'] ??= 0;
        $options['htconfigIdleTimeout'] ??= 0;
        $options['durabilityInterval'] ??= 0;
        $options['durabilityTimeout'] ??= 0;

        return $options;
    }

    protected function doFetch(array $ids): iterable
    {
        $resultsCouchbase = $this->bucket->get($ids);

        $results = [];
        foreach ($resultsCouchbase as $key => $value) {
            if (null !== $value->error) {
                continue;
            }
            $results[$key] = $this->marshaller->unmarshall($value->value);
        }

        return $results;
    }

    protected function doHave(string $id): bool
    {
        return false !== $this->bucket->get($id);
    }

    protected function doClear(string $namespace): bool
    {
        if ('' === $namespace) {
            $this->bucket->manager()->flush();

            return true;
        }

        return false;
    }

    protected function doDelete(array $ids): bool
    {
        $results = $this->bucket->remove(array_values($ids));

        foreach ($results as $key => $result) {
            if (null !== $result->error && static::KEY_NOT_FOUND !== $result->error->getCode()) {
                continue;
            }
            unset($results[$key]);
        }

        return 0 === \count($results);
    }

    protected function doSave(array $values, int $lifetime): array|bool
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }

        $lifetime = $this->normalizeExpiry($lifetime);

        $ko = [];
        foreach ($values as $key => $value) {
            $result = $this->bucket->upsert($key, $value, ['expiry' => $lifetime]);

            if (null !== $result->error) {
                $ko[$key] = $result;
            }
        }

        return [] === $ko ? true : $ko;
    }

    private function normalizeExpiry(int $expiry): int
    {
        if ($expiry && $expiry > static::THIRTY_DAYS_IN_SECONDS) {
            $expiry += time();
        }

        return $expiry;
    }
}
