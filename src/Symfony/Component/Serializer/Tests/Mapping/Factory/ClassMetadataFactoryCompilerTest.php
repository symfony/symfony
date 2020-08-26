<?php

namespace Symfony\Component\Serializer\Tests\Mapping\Factory;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryCompiler;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Tests\Fixtures\Dummy;
use Symfony\Component\Serializer\Tests\Fixtures\MaxDepthDummy;
use Symfony\Component\Serializer\Tests\Fixtures\SerializedNameDummy;
use Symfony\Component\Serializer\Tests\Fixtures\SerializedNameWithGroupsDummy;

final class ClassMetadataFactoryCompilerTest extends TestCase
{
    /**
     * @var string
     */
    private $dumpPath;

    protected function setUp(): void
    {
        $this->dumpPath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'php_serializer_metadata.'.uniqid('CompiledClassMetadataFactory').'.php';
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
        $serializedNameWithGroupsDummyMetadata = $classMetatadataFactory->getMetadataFor(SerializedNameWithGroupsDummy::class);

        $code = (new ClassMetadataFactoryCompiler())->compile([
            $dummyMetadata,
            $maxDepthDummyMetadata,
            $serializedNameDummyMetadata,
            $serializedNameWithGroupsDummyMetadata,
        ]);

        file_put_contents($this->dumpPath, $code);
        $compiledMetadata = require $this->dumpPath;

        $this->assertCount(4, $compiledMetadata);

        $this->assertArrayHasKey(Dummy::class, $compiledMetadata);
        $this->assertEquals([
            [
                'foo' => [[], null, []],
                'bar' => [[], null, []],
                'baz' => [[], null, []],
                'qux' => [[], null, []],
            ],
            null,
        ], $compiledMetadata[Dummy::class]);

        $this->assertArrayHasKey(MaxDepthDummy::class, $compiledMetadata);
        $this->assertEquals([
            [
                'foo' => [[], 2, []],
                'bar' => [[], 3, []],
                'child' => [[], null, []],
            ],
            null,
        ], $compiledMetadata[MaxDepthDummy::class]);

        $this->assertArrayHasKey(SerializedNameDummy::class, $compiledMetadata);

        $this->assertEquals([
            [
                'foo' => [[], null, ['baz' => []]],
                'bar' => [[], null, ['qux' => []]],
                'quux' => [[], null, []],
                'child' => [[], null, []],
            ],
            null,
        ], $compiledMetadata[SerializedNameDummy::class]);

        $this->assertArrayHasKey(SerializedNameWithGroupsDummy::class, $compiledMetadata);

        $this->assertEquals([
            [
                'foo' => [[], null, ['baz' => []]],
                'bar' => [['group1'], null, ['qux' => []]],
                'quux' => [[], null, []],
                'foos' => [[], null, ['bazs' => []]],
                'barWithGroup' => [['group1'], null, ['bargroups' => ['group1']]],
                'quuxWithGroups' => [['group1', 'group2'], null, ['quuxgroups2' => ['group1', 'group2'], 'quuxgroups1' => ['group1']]],
            ],
            null,
        ], $compiledMetadata[SerializedNameWithGroupsDummy::class]);
    }
}
