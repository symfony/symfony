<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\SkippedTestSuiteError;
use Symfony\Component\Cache\Adapter\AbstractAdapter;

/**
 * @group integration
 */
class PredisAdapterSentinelTest extends AbstractRedisAdapterTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(\Predis\Client::class)) {
            throw new SkippedTestSuiteError('The Predis\Client class is required.');
        }
        if (!$hosts = getenv('REDIS_SENTINEL_HOSTS')) {
            throw new SkippedTestSuiteError('REDIS_SENTINEL_HOSTS env var is not defined.');
        }
        if (!$service = getenv('REDIS_SENTINEL_SERVICE')) {
            throw new SkippedTestSuiteError('REDIS_SENTINEL_SERVICE env var is not defined.');
        }

        self::$redis = AbstractAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).']', ['redis_sentinel' => $service, 'class' => \Predis\Client::class]);
    }
}
