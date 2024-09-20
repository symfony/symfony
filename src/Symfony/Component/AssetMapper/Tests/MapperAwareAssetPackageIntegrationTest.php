<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\AssetMapper\Tests\Fixtures\AssetMapperTestAppKernel;
use Symfony\Component\Filesystem\Filesystem;

class MapperAwareAssetPackageIntegrationTest extends TestCase
{
    private AssetMapperTestAppKernel $kernel;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->kernel = new AssetMapperTestAppKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->kernel->getProjectDir().'/var');
    }

    public function testDefaultAssetPackageIsDecorated()
    {
        $packages = $this->kernel->getContainer()->get('public.assets.packages');
        \assert($packages instanceof Packages);
        $this->assertSame('/assets/file1-s0Rct6h.css', $packages->getUrl('file1.css'));
        $this->assertSame('/non-existent.css', $packages->getUrl('non-existent.css'));
    }
}
