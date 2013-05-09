<?php

namespace Symfony\Component\Cache\Tests\Acceptance;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Driver\ArrayDriver;
use Symfony\Component\Cache\Extension\CoreExtension;
use Symfony\Component\Cache\Extension\ExtensionStack;
use Symfony\Component\Cache\Extension\MetadataExtension;
use Symfony\Component\Cache\Extension\TagExtension;

abstract class AcceptanceTest extends \PHPUnit_Framework_TestCase
{
    protected function createCache()
    {
        $extension = new ExtensionStack();
        $extension->register('core', new CoreExtension(), 50);
        $extension->register('metadata', new MetadataExtension(), 25);
        $extension->register('tags', new TagExtension(), -25);

        return new Cache(new ArrayDriver(), $extension);
    }
}
