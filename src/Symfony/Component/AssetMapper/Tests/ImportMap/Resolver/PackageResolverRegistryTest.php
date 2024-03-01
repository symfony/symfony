<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\ImportMap\Resolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverRegistry;

class PackageResolverRegistryTest extends TestCase
{
    public function testCanSetDefaultResolver()
    {
        $defaultResolver = $this->createMock(PackageResolverInterface::class);
        $registry = new PackageResolverRegistry();

        $registry->setDefaultResolver($defaultResolver);

        $this->assertSame($defaultResolver, $registry->getResolver());
    }

    public function testThrowsExceptionWhenNoDefaultResolverIsDefined()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No default resolver is defined.');

        $registry = new PackageResolverRegistry();
        $registry->getResolver();
    }

    public function testCanAddAndRetrieveResolver()
    {
        $resolver = $this->createMock(PackageResolverInterface::class);
        $resolver->expects($this->once())->method('getAlias')->willReturn('custom');

        $registry = new PackageResolverRegistry();

        $registry->addResolver($resolver);

        $this->assertSame($resolver, $registry->getResolver('custom'));
    }

    public function testThrowsExceptionWhenResolverDoesNotExist()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The resolver "nonexistent" does not exist.');

        $registry = new PackageResolverRegistry();
        $registry->getResolver('nonexistent');
    }
}
