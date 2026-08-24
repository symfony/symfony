<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Bridge\MongoDb\Adapter;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Psr\Clock\ClockInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\PruneableInterface;

/**
 * A cache adapter storing items as documents in a MongoDB collection.
 *
 * Each item is a document {_id, data, expires_at}. When a TTL index is created
 * on "expires_at" (see setup()), the server removes expired items by itself.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class MongoDbAdapter extends AbstractAdapter implements PruneableInterface
{
    use MongoDbTrait;

    /**
     * @param string $namespace Prefix prepended to every cache key. When empty, it falls back to the DSN "namespace" query parameter.
     *
     * Available options:
     *  * database_name:   The name of the database [required unless a Collection is given, or set in the DSN path]
     *  * collection_name: The name of the collection [required unless a Collection is given, or set in the DSN "collection_name" query parameter]
     *  * driverOptions:   Array of driver options passed to MongoDB\Client [used when a DSN is given]
     *
     * Any other option is forwarded to the collection, typically a "readPreference", "readConcern" or
     * "writeConcern" given as a MongoDB\Driver\* object. Connection string options (as strings) are read
     * from the DSN query string instead.
     *
     * @see https://www.mongodb.com/docs/php-library/current/reference/method/MongoDBCollection-withOptions/
     */
    public function __construct(
        #[\SensitiveParameter] Collection|Client|Database|string $mongo,
        string $namespace = '',
        int $defaultLifetime = 0,
        array $options = [],
        ?MarshallerInterface $marshaller = null,
        ?ClockInterface $clock = null,
    ) {
        $this->init($mongo, $namespace, $defaultLifetime, $options, $marshaller ?? new DefaultMarshaller(), $clock);
    }

    protected function doSave(array $values, int $lifetime): array|bool
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }

        $expiresAt = $this->expiry($lifetime);

        $operations = [];
        foreach ($values as $id => $value) {
            $document = ['_id' => $id, 'data' => self::createBinary($value)];
            if (null !== $expiresAt) {
                $document['expires_at'] = $expiresAt;
            }

            $operations[] = ['replaceOne' => [
                ['_id' => ['$eq' => $id]],
                $document,
                ['upsert' => true],
            ]];
        }

        $this->collection->bulkWrite($operations);

        return $failed;
    }
}
