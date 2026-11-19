<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Tests\Schema;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Schema\FileHeaderSchemaResolver;

class FileHeaderSchemaResolverTest extends TestCase
{
    #[DataProvider('provideHeaders')]
    public function testHeaderIsMatched(string $content)
    {
        $resolver = new FileHeaderSchemaResolver();

        $this->assertSame('/schema.json', $resolver->resolve($content));
    }

    public static function provideHeaders(): iterable
    {
        yield 'the language server form' => ["# yaml-language-server: \$schema=/schema.json\nversion: 8"];
        yield 'the short form' => ["# \$schema=/schema.json\nversion: 8"];
        yield 'without a space after the hash' => ["#\$schema=/schema.json\nversion: 8"];
        yield 'below another comment' => ["# Some comment\n# \$schema=/schema.json\nversion: 8"];
        yield 'below a blank line' => ["\n# \$schema=/schema.json\nversion: 8"];
        yield 'an indented header' => ["    # \$schema=/schema.json\nversion: 8"];
    }

    #[DataProvider('provideContentWithoutHeader')]
    public function testNoHeaderResolvesToNull(string $content)
    {
        $resolver = new FileHeaderSchemaResolver();

        $this->assertNull($resolver->resolve($content));
    }

    public static function provideContentWithoutHeader(): iterable
    {
        yield 'no comment at all' => ['version: 8'];
        yield 'an unrelated comment' => ["# Some comment\nversion: 8"];
        // The header only applies at the top of the file, before any YAML content.
        yield 'below the content' => ["version: 8\n# \$schema=/schema.json"];
        yield 'an empty document' => [''];
    }

    public function testRelativeHeaderIsResolvedAgainstTheFile()
    {
        $resolver = new FileHeaderSchemaResolver();

        $this->assertSame('/project/config/schema.json', $resolver->resolve('# $schema=schema.json', '/project/config/routes.yaml'));
        $this->assertSame('/project/config/../schema.json', $resolver->resolve('# $schema=../schema.json', '/project/config/routes.yaml'));
        $this->assertSame('/project/config/http-schema.json', $resolver->resolve('# $schema=http-schema.json', '/project/config/routes.yaml'));
        $this->assertSame('/project/config/C:schema.json', $resolver->resolve('# $schema=C:schema.json', '/project/config/routes.yaml'));
    }

    #[DataProvider('provideEncodedHeaders')]
    public function testPercentEncodedHeaderIsDecoded(string $schema, string $expected)
    {
        $resolver = new FileHeaderSchemaResolver();

        $this->assertSame($expected, $resolver->resolve('# $schema='.$schema, '/project/config/routes.yaml'));
    }

    public static function provideEncodedHeaders(): iterable
    {
        yield 'a relative path with spaces' => ['schema%20with%20space.json', '/project/config/schema with space.json'];
        yield 'a relative path with a hash' => ['schema%23with%23hash.json', '/project/config/schema#with#hash.json'];
        yield 'a relative path with a percent sign' => ['schema%25with%25percent.json', '/project/config/schema%with%percent.json'];
        yield 'a relative path with a plus sign' => ['schema+with+plus.json', '/project/config/schema+with+plus.json'];
        yield 'a relative path with non-ASCII characters' => ['sch%C3%A9ma.json', '/project/config/schéma.json'];
        yield 'an absolute unix path with spaces' => ['/schema%20with%20space.json', '/schema with space.json'];
        yield 'a url is left encoded' => ['https://example.com/schema%20with%20space.json', 'https://example.com/schema%20with%20space.json'];
    }

    #[DataProvider('provideHeadersResolvedLikeYamlLanguageServer')]
    public function testHeaderIsResolvedLikeYamlLanguageServer(string $schema, string $expected)
    {
        $resolver = new FileHeaderSchemaResolver();

        $this->assertSame($expected, $resolver->resolve('# $schema='.$schema, '/project/config/routes.yaml'));
    }

    public static function provideHeadersResolvedLikeYamlLanguageServer(): iterable
    {
        yield 'a value starting with "file:" is kept as is' => ['file:schema.json', 'file:schema.json'];
        yield 'another scheme is a relative path' => ['foo://bar.json', '/project/config/foo://bar.json'];
        yield 'a stream wrapper is a relative path' => ['phar://archive.phar/schema.json', '/project/config/phar://archive.phar/schema.json'];
        yield 'a "#" starts a fragment' => ['schema.json#/definitions/foo', 'file:///project/config/schema.json#/definitions/foo'];
        yield 'a fragment after an absolute path' => ['/schema.json#/definitions/foo', 'file:///schema.json#/definitions/foo'];
        yield 'a fragment is not decoded' => ['schema%20with%20space.json#/definitions/a%20b', 'file:///project/config/schema%20with%20space.json#/definitions/a%20b'];
        yield 'an encoded "#" before a fragment stays in the file name' => ['schema%23hash.json#/definitions/foo', 'file:///project/config/schema%23hash.json#/definitions/foo'];
        yield 'an empty fragment is dropped' => ['schema.json#', '/project/config/schema.json'];
        yield 'a "#" as first character is part of the file name' => ['#schema.json', '/project/config/#schema.json'];
        yield 'a "#" as first character disables the fragment' => ['#schema.json#/definitions/a%20b', '/project/config/#schema.json#/definitions/a b'];
        yield 'what follows a second "#" is dropped' => ['schema.json#/definitions/foo#bar', 'file:///project/config/schema.json#/definitions/foo'];
        yield 'an encoded leading slash is a relative path' => ['%2Fschema.json', '/project/config//schema.json'];
    }

    public function testFragmentAfterAWindowsAbsolutePath()
    {
        $resolver = new FileHeaderSchemaResolver();

        $expected = '\\' === \DIRECTORY_SEPARATOR ? 'file:///C:/project/schema%20a.json#/definitions/foo' : 'file:///project/config/C%3A/project/schema%20a.json#/definitions/foo';
        $this->assertSame($expected, $resolver->resolve('# $schema=C:/project/schema%20a.json#/definitions/foo', '/project/config/routes.yaml'));
    }

    public function testFragmentOfARelativePathIsResolvedAgainstTheWorkingDirectory()
    {
        $resolver = new FileHeaderSchemaResolver();
        $cwd = '/'.rawurlencode(basename(getcwd()));

        $this->assertStringStartsWith('file:///', $uri = $resolver->resolve('# $schema=schema.json#/definitions/foo'));
        $this->assertStringEndsWith($cwd.'/schema.json#/definitions/foo', $uri);
        $this->assertStringStartsWith('file:///', $uri = $resolver->resolve('# $schema=schema.json#/definitions/foo', 'config/routes.yaml'));
        $this->assertStringEndsWith($cwd.'/config/schema.json#/definitions/foo', $uri);
    }

    #[DataProvider('provideWindowsAbsolutePaths')]
    public function testWindowsAbsolutePathIsAbsoluteOnlyOnWindows(string $schema, string $path)
    {
        $resolver = new FileHeaderSchemaResolver();

        $expected = '\\' === \DIRECTORY_SEPARATOR ? $path : '/project/config/'.$path;
        $this->assertSame($expected, $resolver->resolve('# $schema='.$schema, '/project/config/routes.yaml'));
    }

    public static function provideWindowsAbsolutePaths(): iterable
    {
        yield 'a drive letter and a slash' => ['C:/project/schema.json', 'C:/project/schema.json'];
        yield 'a drive letter and a backslash' => ['C:\\project\\schema.json', 'C:\\project\\schema.json'];
        yield 'a lowercase drive letter' => ['c:/project/schema.json', 'c:/project/schema.json'];
        yield 'a leading backslash' => ['\\project\\schema.json', '\\project\\schema.json'];
        yield 'a drive letter and an encoded space' => ['C:/schema%20with%20space.json', 'C:/schema with space.json'];
    }

    #[DataProvider('provideNonRelativeHeaders')]
    public function testNonRelativeHeaderIsKeptAsIs(string $schema)
    {
        $resolver = new FileHeaderSchemaResolver();

        $this->assertSame($schema, $resolver->resolve('# $schema='.$schema, '/project/config/routes.yaml'));
    }

    public static function provideNonRelativeHeaders(): iterable
    {
        yield 'a unix absolute path' => ['/schema.json'];
        yield 'a url' => ['https://example.com/schema.json'];
        yield 'a http url' => ['http://example.com/schema.json'];
    }

    public function testHeaderIsNotResolvedWithoutAFile()
    {
        $resolver = new FileHeaderSchemaResolver();

        $this->assertSame('schema.json', $resolver->resolve('# $schema=schema.json'));
    }
}
