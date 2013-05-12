<?php

namespace Symfony\Component\Cache\Tests\Acceptance;

use Stash\Driver\Ephemeral as StashArrayCache;
use Doctrine\Common\Cache\ArrayCache as DoctrineArrayCache;
use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Driver\ArrayDriver;
use Symfony\Component\Cache\Driver\DoctrineDriver;
use Symfony\Component\Cache\Driver\StashDriver;
use Symfony\Component\Cache\Extension\CoreExtension;
use Symfony\Component\Cache\Extension\ExtensionStack;
use Symfony\Component\Cache\Extension\MetadataExtension;
use Symfony\Component\Cache\Extension\TagExtension;

class DataProvider
{
    public static function provideCaches()
    {
        $extension = new ExtensionStack();
        $extension->register('core', new CoreExtension(), 50);
        $extension->register('metadata', new MetadataExtension(), 25);
        $extension->register('tags', new TagExtension(), -25);

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
