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

use Symfony\Bridge\PhpUnit\ForwardCompatTestTrait;

class PredisClusterAdapterTest extends AbstractRedisAdapterTest
{
    use ForwardCompatTestTrait;

    private static function doSetUpBeforeClass()
    {
        parent::setupBeforeClass();
        self::$redis = new \Predis\Client([['host' => getenv('REDIS_HOST')]]);
    }

    private static function doTearDownAfterClass()
    {
        self::$redis = null;
    }
}
