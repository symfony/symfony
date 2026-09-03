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

use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Marshaller\TagAwareMarshaller;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Traits\MongoDbTrait;

/**
 * A tag aware cache adapter storing items and their tags in a MongoDB collection.
 *
 * Tags are stored as an array on each item document, so invalidating a tag is a
 * single delete query on the "tags" field. Create the indexes with setup().
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class MongoDbTagAwareAdapter extends AbstractTagAwareAdapter implements PruneableInterface
{
    use MongoDbTrait {
        setup as private setupExpiresAtIndex;
    }

    private function createMarshaller(?MarshallerInterface $marshaller): MarshallerInterface
    {
        return new TagAwareMarshaller($marshaller);
    }

    public function setup(): void
    {
        $this->setupExpiresAtIndex();

        // A partial multikey index skips items stored without any tag.
        $this->collection->createIndex(
            ['tags' => 1],
            ['partialFilterExpression' => ['tags' => ['$exists' => true]]],
        );
    }

    protected function doSave(array $values, int $lifetime, array $addTagData = [], array $removeTagData = []): array
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed ?? [];
        }

        $expiresAt = $this->expiry($lifetime);

        // group the tags to add per item id
        $addedTags = [];
        foreach ($addTagData as $tagId => $ids) {
            foreach ($ids as $id) {
                if (!$failed || !\in_array($id, $failed, true)) {
                    $addedTags[$id][] = $tagId;
                }
            }
        }

        $operations = [];
        foreach ($values as $id => $value) {
            $set = ['data' => self::createBinary($value)];
            $update = ['$setOnInsert' => ['_id' => $id]];

            if (null !== $expiresAt) {
                $set['expires_at'] = $expiresAt;
            } else {
                $update['$unset'] = ['expires_at' => ''];
            }
            $update['$set'] = $set;

            if ($addedTags[$id] ?? false) {
                $update['$addToSet'] = ['tags' => ['$each' => $addedTags[$id]]];
            }

            $operations[] = ['updateOne' => [
                ['_id' => ['$eq' => $id]],
                $update,
                ['upsert' => true],
            ]];
        }

        foreach ($removeTagData as $tagId => $ids) {
            if ($failed) {
                $ids = array_diff($ids, $failed);
            }
            if ($ids) {
                $operations[] = ['updateMany' => [
                    ['_id' => ['$in' => array_values($ids)]],
                    ['$pull' => ['tags' => ['$in' => [$tagId]]]],
                ]];
            }
        }

        $this->collection->bulkWrite($operations);

        return $failed ?? [];
    }

    protected function doDeleteTagRelations(array $tagData): bool
    {
        // Tags are embedded in the item documents, so deleting an item removes
        // its tag relations at the same time. Nothing to do here.
        return true;
    }

    protected function doInvalidate(array $tagIds): bool
    {
        $this->collection->deleteMany(['tags' => ['$in' => array_values($tagIds)]]);

        return true;
    }
}
