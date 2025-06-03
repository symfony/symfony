<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Path;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolver;

class PublicAssetsPathResolverTest extends TestCase
{
    public function testResolvePublicPath()
    {
        $resolver = new PublicAssetsPathResolver(
            '/assets-prefix/',
        );
        $this->assertSame('/assets-prefix/', $resolver->resolvePublicPath(''));
        $this->assertSame('/assets-prefix/foo/bar', $resolver->resolvePublicPath('/foo/bar'));
        $this->assertSame('/assets-prefix/foo/bar', $resolver->resolvePublicPath('foo/bar'));

        $resolver = new PublicAssetsPathResolver(
            'assets-prefix', // The leading and trailing slash should be added automatically
        );
        $this->assertSame('/assets-prefix/', $resolver->resolvePublicPath(''));
        $this->assertSame('/assets-prefix/foo/bar', $resolver->resolvePublicPath('/foo/bar'));
        $this->assertSame('/assets-prefix/foo/bar', $resolver->resolvePublicPath('foo/bar'));
    }
}
