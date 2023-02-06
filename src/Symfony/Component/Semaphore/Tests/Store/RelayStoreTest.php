<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests\Store;

use PHPUnit\Framework\SkippedTestSuiteError;
use Relay\Relay;

/**
 * @requires extension relay
 */
class RelayStoreTest extends AbstractRedisStoreTestCase
{
    protected function setUp(): void
    {
        $this->getRedisConnection()->flushDB();
    }

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
