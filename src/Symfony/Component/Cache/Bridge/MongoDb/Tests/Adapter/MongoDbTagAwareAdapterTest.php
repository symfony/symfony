<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Bridge\MongoDb\Tests\Adapter;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Bridge\MongoDb\Adapter\MongoDbTagAwareAdapter;
use Symfony\Component\Cache\Bridge\MongoDb\Internal\MongoDbTrait;
use Symfony\Component\Cache\Test\TagAwareTestTrait;

#[RequiresPhpExtension('mongodb')]
#[Group('integration')]
#[TimeSensitive(MongoDbTrait::class)]
#[TimeSensitive(AbstractAdapter::class)]
class MongoDbTagAwareAdapterTest extends MongoDbAdapterTest
{
    use TagAwareTestTrait;

    public function createCachePool(int $defaultLifetime = 0, ?string $testMethod = null): CacheItemPoolInterface
    {
        return new MongoDbTagAwareAdapter(self::$client, str_replace('\\', '.', static::class), $defaultLifetime, [
            'database_name' => self::DATABASE,
            'collection_name' => self::COLLECTION,
        ]);
    }
}
