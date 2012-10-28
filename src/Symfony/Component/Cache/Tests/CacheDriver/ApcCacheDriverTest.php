<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\CacheDriver;

use Symfony\Component\Cache\Driver\ApcDriver;

class ApcCacheDriverTest extends AbstractCacheDriverTest
{
    public function setUp()
    {
        if (!extension_loaded('apc') || !ini_get('apc.enabled') || !ini_get('apc.enable_cli')) {
            $this->markTestSkipped('Please install and enable APC for CLI to execute this test');
        }

        parent::setUp();
    }

    public function _getTestDriver()
    {
        return new ApcDriver();
    }
}