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

use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Traits\MongoDbTrait;

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

    private function createMarshaller(?MarshallerInterface $marshaller): MarshallerInterface
    {
        return $marshaller ?? new DefaultMarshaller();
    }

    protected function doSave(array $values, int $lifetime): array|bool
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed ?? [];
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

        return $failed ?? [];
    }
}
