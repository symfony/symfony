<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Factory;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryCompiler;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\MaxDepthDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\SerializedNameDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Dummy;

final class ClassMetadataFactoryCompilerTest extends TestCase
{
    /**
     * @var string
     */
    private $dumpPath;

    protected function setUp(): void
    {
        $this->dumpPath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'php_serializer_metadata.'.uniqid('CompiledClassMetadataFactory', true).'.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->dumpPath);
    }

    public function testItDumpMetadata()
    {
        $classMetatadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $dummyMetadata = $classMetatadataFactory->getMetadataFor(Dummy::class);
        $maxDepthDummyMetadata = $classMetatadataFactory->getMetadataFor(MaxDepthDummy::class);
        $serializedNameDummyMetadata = $classMetatadataFactory->getMetadataFor(SerializedNameDummy::class);

        $code = (new ClassMetadataFactoryCompiler())->compile([
            $dummyMetadata,
            $maxDepthDummyMetadata,
            $serializedNameDummyMetadata,
        ]);

        file_put_contents($this->dumpPath, $code);
        $compiledMetadata = require $this->dumpPath;

        $this->assertCount(3, $compiledMetadata);

        $this->assertArrayHasKey(Dummy::class, $compiledMetadata);
        $this->assertEquals([
            [
                'foo' => [[], null, null],
                'bar' => [[], null, null],
                'baz' => [[], null, null],
                'qux' => [[], null, null],
            ],
            null,
        ], $compiledMetadata[Dummy::class]);

        $this->assertArrayHasKey(MaxDepthDummy::class, $compiledMetadata);
        $this->assertEquals([
            [
                'foo' => [[], 2, null],
                'bar' => [[], 3, null],
                'child' => [[], null, null],
            ],
            null,
        ], $compiledMetadata[MaxDepthDummy::class]);

        $this->assertArrayHasKey(SerializedNameDummy::class, $compiledMetadata);
        $this->assertEquals([
            [
                'foo' => [[], null, 'baz'],
                'bar' => [[], null, 'qux'],
                'quux' => [[], null, null],
                'child' => [[], null, null],
            ],
            null,
        ], $compiledMetadata[SerializedNameDummy::class]);
    }
}
