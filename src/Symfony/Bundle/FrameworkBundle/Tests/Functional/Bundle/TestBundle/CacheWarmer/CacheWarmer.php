<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\CacheWarmer;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class CacheWarmer implements CacheWarmerInterface
{
    public function warmUp($cacheDir)
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($cacheDir.'/cache_warmer');
    }

    public function isOptional()
    {
        return true;
    }
}