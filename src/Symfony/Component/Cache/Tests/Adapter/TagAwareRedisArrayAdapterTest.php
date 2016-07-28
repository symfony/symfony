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

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareRedisAdapter;

class TagAwareRedisArrayAdapterTest extends AbstractRedisAdapterTest
{
    use TagAwareAdapterTestTrait;

    protected $skippedTests = array(
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testSaveWithoutExpire' => 'Assumes a shared cache which ArrayAdapter is not.',
    );

    public static function setupBeforeClass()
    {
        parent::setupBeforeClass();
        self::$redis = new \Redis();
        self::$redis->connect('127.0.0.1');
    }

    public function createCachePool($defaultLifeTime = 0)
    {
        if (defined('HHVM_VERSION')) {
            $this->skippedTests['testDeferredSaveWithoutCommit'] = 'Fails on HHVM';
        }

        return new TagAwareRedisAdapter(self::$redis, str_replace('\\', '.', __CLASS__), 0, new ArrayAdapter($defaultLifeTime));
    }
}
