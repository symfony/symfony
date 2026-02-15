<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Bridge\DynamoDb\Tests\Functional\Store;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Lock\Bridge\DynamoDb\Store\DynamoDbStore;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

#[Group('integration')]
class DynamoDbStoreTest extends AbstractStoreTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!getenv('LOCK_DYNAMODB_DSN')) {
            self::markTestSkipped('DynamoDB server not found.');
        }

        $store = new DynamoDbStore(getenv('LOCK_DYNAMODB_DSN'));
        $store->createTable();
    }

    protected function getStore(): PersistingStoreInterface
    {
        return new DynamoDbStore(getenv('LOCK_DYNAMODB_DSN'));
    }
}
