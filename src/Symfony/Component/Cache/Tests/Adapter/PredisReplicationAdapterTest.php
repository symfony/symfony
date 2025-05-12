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

/**
 * @group integration
 */
class PredisReplicationAdapterTest extends AbstractRedisAdapterTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!$hosts = getenv('REDIS_REPLICATION_HOSTS')) {
            self::markTestSkipped('REDIS_REPLICATION_HOSTS env var is not defined.');
        }

        $hosts = explode(' ', getenv('REDIS_REPLICATION_HOSTS'));
        $lastArrayKey = array_key_last($hosts);
        $hostTable = [];
        foreach ($hosts as $key => $host) {
            $hostInformation = array_combine(['host', 'port'], explode(':', $host));
            if ($lastArrayKey === $key) {
                $hostInformation['role'] = 'master';
            }
            $hostTable[] = $hostInformation;
        }

        self::$redis = new \Predis\Client($hostTable, ['replication' => 'predis', 'prefix' => 'prefix_']);
    }
}
