<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Serializer\CacheWarmer\CompiledClassMetadataCacheWarmer;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryCompiler;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

final class CompiledClassMetadataCacheWarmerTest extends TestCase
{
    public function testItImplementsCacheWarmerInterface()
    {
        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);
        $filesystem = self::createMock(Filesystem::class);

        $compiledClassMetadataCacheWarmer = new CompiledClassMetadataCacheWarmer([], $classMetadataFactory, new ClassMetadataFactoryCompiler(), $filesystem);

        self::assertInstanceOf(CacheWarmerInterface::class, $compiledClassMetadataCacheWarmer);
    }

    public function testItIsAnOptionalCacheWarmer()
    {
        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);
        $filesystem = self::createMock(Filesystem::class);

        $compiledClassMetadataCacheWarmer = new CompiledClassMetadataCacheWarmer([], $classMetadataFactory, new ClassMetadataFactoryCompiler(), $filesystem);

        self::assertTrue($compiledClassMetadataCacheWarmer->isOptional());
    }

    public function testItDumpCompiledClassMetadatas()
    {
        $classMetadataFactory = self::createMock(ClassMetadataFactoryInterface::class);

        $code = <<<EOF
<?php

// This file has been auto-generated by the Symfony Serializer Component.

return [
];
EOF;

        $filesystem = self::createMock(Filesystem::class);
        $filesystem
            ->expects(self::once())
            ->method('dumpFile')
            ->with('/var/cache/prod/serializer.class.metadata.php', $code)
        ;

        $compiledClassMetadataCacheWarmer = new CompiledClassMetadataCacheWarmer([], $classMetadataFactory, new ClassMetadataFactoryCompiler(), $filesystem);

        $compiledClassMetadataCacheWarmer->warmUp('/var/cache/prod');
    }
}
