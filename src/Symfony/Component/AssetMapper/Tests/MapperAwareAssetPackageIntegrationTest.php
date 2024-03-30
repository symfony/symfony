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

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\AssetMapper\Tests\Fixtures\AssetMapperTestAppKernel;

class MapperAwareAssetPackageIntegrationTest extends KernelTestCase
{
    public function testDefaultAssetPackageIsDecorated()
    {
        $kernel = new AssetMapperTestAppKernel('test', true);
        $kernel->boot();

        $packages = $kernel->getContainer()->get('public.assets.packages');
        \assert($packages instanceof Packages);
        $this->assertSame('/assets/file1-b3445cb7a86a0795a7af7f2004498aef.css', $packages->getUrl('file1.css'));
        $this->assertSame('/non-existent.css', $packages->getUrl('non-existent.css'));
    }
}
