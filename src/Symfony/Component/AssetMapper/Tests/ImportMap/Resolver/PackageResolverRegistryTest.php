<?php

namespace Symfony\Component\AssetMapper\Tests\ImportMap\Resolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverRegistry;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;

class PackageResolverRegistryTest extends TestCase
{
    public function testCanSetDefaultResolver(): void
    {
        $defaultResolver = $this->createMock(PackageResolverInterface::class);
        $registry = new PackageResolverRegistry();

        $registry->setDefaultResolver($defaultResolver);

        $this->assertSame($defaultResolver, $registry->getResolver());
    }

    public function testThrowsExceptionWhenNoDefaultResolverIsDefined(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No default resolver is defined.');

        $registry = new PackageResolverRegistry();
        $registry->getResolver();
    }

    public function testCanAddAndRetrieveResolver(): void
    {
        $resolver = $this->createMock(PackageResolverInterface::class);
        $registry = new PackageResolverRegistry();

        $registry->addResolver('custom', $resolver);

        $this->assertSame($resolver, $registry->getResolver('custom'));
    }

    public function testThrowsExceptionWhenResolverDoesNotExist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The resolver "nonexistent" does not exist.');

        $registry = new PackageResolverRegistry();
        $registry->getResolver('nonexistent');
    }
}
