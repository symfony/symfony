<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Compiler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\AssetMapperCompiler;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\Compiler\JavaScriptImportPathCompiler;
use Symfony\Component\AssetMapper\Exception\CircularAssetsException;
use Symfony\Component\AssetMapper\Exception\RuntimeException;
use Symfony\Component\AssetMapper\Factory\MappedAssetFactory;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolver;
use Symfony\Component\Filesystem\Filesystem;

class JavaScriptImportMetaResolveTest extends TestCase
{
    #[DataProvider('provideRelativeReferences')]
    public function testRelativeReferencesUseTheVersionedAssetPath(string $source, string $expected)
    {
        $asset = new MappedAsset('app.js', '/project/assets/app.js', '/assets/app.js');
        $dependency = new MappedAsset('templates/template.twig', '/project/assets/template.twig', '/assets/templates/template.twig', '/assets/templates/template-hash.twig');
        $mapper = $this->createMock(AssetMapperInterface::class);
        $mapper->expects($this->once())->method('getAssetFromSourcePath')->with('/project/assets/template.twig')->willReturn($dependency);
        $compiler = new JavaScriptImportPathCompiler($this->createStub(ImportMapConfigReader::class));

        $this->assertSame($expected, $compiler->compile($source, $asset, $mapper));
        $this->assertSame([$dependency], $asset->getDependencies());
        $this->assertSame([], $asset->getJavaScriptImports());
    }

    public static function provideRelativeReferences(): iterable
    {
        yield 'double quotes' => ['const url = import.meta.resolve("./template.twig");', 'const url = import.meta.resolve("./templates/template-hash.twig");'];
        yield 'single quotes' => ["fetch(import.meta.resolve('./template.twig'))", 'fetch(import.meta.resolve("./templates/template-hash.twig"))'];
        yield 'static template' => ['import.meta.resolve(`./template.twig`)', 'import.meta.resolve("./templates/template-hash.twig")'];
        yield 'whitespace' => ["import . meta . resolve (\n './template.twig' \n)", "import . meta . resolve (\n \"./templates/template-hash.twig\" \n)"];
        yield 'query and fragment' => ['import.meta.resolve("./template.twig?raw=1#section")', 'import.meta.resolve("./templates/template-hash.twig?raw=1#section")'];
        yield 'dollar braces in a string' => ['import.meta.resolve("./template.twig?name=${value}")', 'import.meta.resolve("./templates/template-hash.twig?name=${value}")'];
        yield 'same path in query' => ['import.meta.resolve("./template.twig?source=./template.twig")', 'import.meta.resolve("./templates/template-hash.twig?source=./template.twig")'];
        yield 'parent directory' => ['import.meta.resolve("../assets/template.twig")', 'import.meta.resolve("./templates/template-hash.twig")'];
    }

    #[DataProvider('provideIgnoredReferences')]
    public function testOtherExpressionsAreUnchanged(string $source)
    {
        $asset = new MappedAsset('app.js', '/project/assets/app.js', '/assets/app.js');
        $mapper = $this->createMock(AssetMapperInterface::class);
        $mapper->expects($this->never())->method('getAssetFromSourcePath');
        $compiler = new JavaScriptImportPathCompiler($this->createStub(ImportMapConfigReader::class));

        $this->assertSame($source, $compiler->compile($source, $asset, $mapper));
        $this->assertSame([], $asset->getDependencies());
    }

    public static function provideIgnoredReferences(): iterable
    {
        yield 'line comment' => ['// import.meta.resolve("./template.twig")'];
        yield 'block comment' => ['/* import.meta.resolve("./template.twig") */'];
        yield 'string' => ["const text = 'import.meta.resolve(\"./template.twig\")';"];
        yield 'template text' => ['const text = `import.meta.resolve("./template.twig")`;'];
        yield 'template substitution' => ['import.meta.resolve(`./${name}.twig`)'];
        yield 'escaped path' => ['import.meta.resolve("./templ\\u0061te.twig")'];
        yield 'variable' => ['import.meta.resolve(path)'];
        yield 'concatenation' => ['import.meta.resolve("./template.twig" + suffix)'];
        yield 'absolute URL' => ['import.meta.resolve("https://example.com/template.twig")'];
        yield 'absolute path' => ['import.meta.resolve("/template.twig")'];
        yield 'bare name' => ['import.meta.resolve("template.twig")'];
        yield 'other identifier' => ['myimport.meta.resolve("./template.twig")'];
        yield 'property access' => ['object.import.meta.resolve("./template.twig")'];
    }

    public function testMultipleReferencesAndOrdinaryImportsAreCompiledTogether()
    {
        $asset = new MappedAsset('app.js', '/project/assets/app.js', '/assets/app.js');
        $dependency = new MappedAsset('template.twig', '/project/assets/template.twig', '/assets/template.twig', '/assets/template-hash.twig');
        $module = new MappedAsset('module.js', '/project/assets/module.js', '/assets/module.js', '/assets/module-hash.js');
        $mapper = $this->createStub(AssetMapperInterface::class);
        $mapper->method('getAssetFromSourcePath')->willReturnMap([
            ['/project/assets/template.twig', $dependency],
            ['/project/assets/module.js', $module],
        ]);
        $compiler = new JavaScriptImportPathCompiler($this->createStub(ImportMapConfigReader::class));
        $source = 'fetch(import.meta.resolve("./template.twig")); import "./module.js"; fetch(import.meta.resolve("./template.twig?raw"));';

        $this->assertSame('fetch(import.meta.resolve("./template-hash.twig")); import "./module.js"; fetch(import.meta.resolve("./template-hash.twig?raw"));', $compiler->compile($source, $asset, $mapper));
        $this->assertCount(1, $asset->getJavaScriptImports());
        $this->assertSame('module.js', $asset->getJavaScriptImports()[0]->assetLogicalPath);
        $this->assertSame([$dependency, $dependency], $asset->getDependencies());
    }

    public function testMissingReferencesFollowTheConfiguredPolicy()
    {
        $asset = new MappedAsset('app.js', '/project/assets/app.js', '/assets/app.js');
        $mapper = $this->createStub(AssetMapperInterface::class);
        $source = 'import.meta.resolve("./missing.twig")';
        $reader = $this->createStub(ImportMapConfigReader::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Unable to find asset "./missing.twig" imported from "/project/assets/app.js".');

        $this->assertSame($source, new JavaScriptImportPathCompiler($reader, AssetCompilerInterface::MISSING_IMPORT_WARN, $logger)->compile($source, $asset, $mapper));
        $this->assertSame($source, new JavaScriptImportPathCompiler($reader, AssetCompilerInterface::MISSING_IMPORT_IGNORE)->compile($source, $asset, $mapper));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to find asset "./missing.twig"');
        new JavaScriptImportPathCompiler($reader, AssetCompilerInterface::MISSING_IMPORT_STRICT)->compile($source, $asset, $mapper);
    }

    public function testReferencedContentChangesTheCompiledJavaScriptDigest()
    {
        $directory = sys_get_temp_dir().'/sf_import_meta_resolve_test';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($directory.'/app.js', 'fetch(import.meta.resolve("./template.twig"));');
        $filesystem->dumpFile($directory.'/template.twig', 'first version');
        [$mapper, $factory] = $this->createAssetMapper($directory);

        try {
            $first = $mapper->getAsset('app.js');
            $template = $mapper->getAsset('template.twig');
            $this->assertSame('fetch(import.meta.resolve("./'.basename($template->publicPath).'"));', $first->content);
            $this->assertSame([$template], $first->getDependencies());
            $this->assertSame([], $first->getJavaScriptImports());

            $filesystem->dumpFile($directory.'/template.twig', 'second version');
            $factory->reset();
            $second = $mapper->getAsset('app.js');
            $this->assertNotSame($first->publicPath, $second->publicPath);
            $this->assertNotSame($first->content, $second->content);
        } finally {
            $filesystem->remove($directory);
        }
    }

    #[DataProvider('provideCircularReferences')]
    public function testCircularReferencesAreRejected(array $files)
    {
        $directory = sys_get_temp_dir().'/sf_import_meta_resolve_circular_test';
        $filesystem = new Filesystem();
        foreach ($files as $path => $content) {
            $filesystem->dumpFile($directory.'/'.$path, $content);
        }
        [$mapper] = $this->createAssetMapper($directory);

        try {
            $this->expectException(CircularAssetsException::class);
            $mapper->getAsset('app.js');
        } finally {
            $filesystem->remove($directory);
        }
    }

    public static function provideCircularReferences(): iterable
    {
        yield 'self reference' => [['app.js' => 'import.meta.resolve("./app.js")']];
        yield 'two files' => [[
            'app.js' => 'import.meta.resolve("./other.js")',
            'other.js' => 'import.meta.resolve("./app.js")',
        ]];
    }

    private function createAssetMapper(string $directory): array
    {
        $mapper = null;
        $compiler = new AssetMapperCompiler([new JavaScriptImportPathCompiler($this->createStub(ImportMapConfigReader::class))], static function () use (&$mapper): AssetMapperInterface {
            return $mapper;
        });
        $factory = new MappedAssetFactory(new PublicAssetsPathResolver(), $compiler, $directory.'/vendor');
        $mapper = new AssetMapper(new AssetMapperRepository([$directory => ''], $directory), $factory, new CompiledAssetMapperConfigReader($directory.'/public'));

        return [$mapper, $factory];
    }
}
