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
        yield 'a windows absolute path' => ['C:/project/schema.json'];
        yield 'a windows absolute path with backslashes' => ['C:\\project\\schema.json'];
        yield 'a url' => ['https://example.com/schema.json'];
    }

    public function testHeaderIsNotResolvedWithoutAFile()
    {
        $resolver = new FileHeaderSchemaResolver();

        $this->assertSame('schema.json', $resolver->resolve('# $schema=schema.json'));
    }
}
