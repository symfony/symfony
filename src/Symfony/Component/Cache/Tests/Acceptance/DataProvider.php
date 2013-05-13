<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Acceptance;

use Stash\Driver\Ephemeral as StashArrayCache;
use Doctrine\Common\Cache\ArrayCache as DoctrineArrayCache;
use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Driver\ArrayDriver;
use Symfony\Component\Cache\Driver\DoctrineDriver;
use Symfony\Component\Cache\Driver\StashDriver;
use Symfony\Component\Cache\Extension\CoreExtension;
use Symfony\Component\Cache\Extension\ExtensionStack;
use Symfony\Component\Cache\Extension\LockExtension;
use Symfony\Component\Cache\Extension\MetadataExtension;
use Symfony\Component\Cache\Extension\TagExtension;

class DataProvider
{
    public static function provideCaches()
    {
        $extension = new ExtensionStack();
        $extension->register(new CoreExtension(), 50);
        $extension->register(new MetadataExtension(), 25);
        $extension->register(new LockExtension(), 0);
        $extension->register(new TagExtension(), -25);

        $drivers = array(
            new ArrayDriver(),
            new StashDriver(new StashArrayCache),
            new DoctrineDriver(new DoctrineArrayCache()),
        );

        $caches = array();
        foreach ($drivers as $driver) {
            $caches[] = array(new Cache($driver, clone $extension));
        }

        return $caches;
    }
}
