<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Store;

use PHPUnit\Framework\SkippedTestSuiteError;
use Relay\Relay;
use Symfony\Component\Lock\Tests\Store\AbstractRedisStoreTestCase;
use Symfony\Component\Lock\Tests\Store\SharedLockStoreTestTrait;

/**
 * @requires extension relay
 *
 * @group integration
 */
class RelayStoreTest extends AbstractRedisStoreTestCase
{
    use SharedLockStoreTestTrait;

    public static function setUpBeforeClass(): void
    {
        try {
            new Relay(...explode(':', getenv('REDIS_HOST')));
        } catch (\Relay\Exception $e) {
            throw new SkippedTestSuiteError($e->getMessage());
        }
    }

    protected function getRedisConnection(): Relay
    {
        return new Relay(...explode(':', getenv('REDIS_HOST')));
    }
}
