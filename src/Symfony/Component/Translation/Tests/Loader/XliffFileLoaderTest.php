<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\XliffFileLoader;

class XliffFileLoaderTest extends TestCase
{
    public function testLoadFile(): void
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
        $this->assertSame([], libxml_get_errors());
        $this->assertContainsOnlyString($catalogue->all('domain1'));
    }

    public function testLoadRawXliff(): void
    {
        $loader = new XliffFileLoader();
        $resource = <<<XLIFF
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" datatype="plaintext" original="file.ext">
                <body>
                  <trans-unit id="1">
                    <source>foo</source>
                    <target>bar</target>
                  </trans-unit>
                  <trans-unit id="2">
                    <source>extra</source>
                  </trans-unit>
                  <trans-unit id="3">
                    <source>key</source>
                    <target></target>
                  </trans-unit>
                  <trans-unit id="4">
                    <source>test</source>
                    <target state="needs-translation">with</target>
                    <note>note</note>
                  </trans-unit>
                  <trans-unit id="5">
                    <source>baz</source>
                    <target state="needs-translation">baz</target>
                  </trans-unit>
                  <trans-unit id="6" resname="buz">
                    <source>baz</source>
                    <target state="needs-translation">buz</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>
            XLIFF;

        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertSame([], libxml_get_errors());
        $this->assertContainsOnlyString($catalogue->all('domain1'));
        $this->assertSame(['foo', 'extra', 'key', 'test'], array_keys($catalogue->all('domain1')));
    }

    public function testLoadWithInternalErrorsEnabled(): void
    {
        $internalErrors = libxml_use_internal_errors(true);

        $this->assertSame([], libxml_get_errors());

        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
        $this->assertSame([], libxml_get_errors());

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
    }

    public function testLoadWithExternalEntitiesDisabled(): void
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadWithResname(): void
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/resname.xlf', 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo', 'qux' => 'qux source'], $catalogue->all('domain1'));
    }

    public function testIncompleteResource(): void
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/resources.xlf', 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar', 'extra' => 'extra', 'key' => '', 'test' => 'with'], $catalogue->all('domain1'));
    }

    public function testEncoding(): void
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/encoding.xlf', 'en', 'domain1');

        $this->assertEquals(mb_convert_encoding('föö', 'ISO-8859-1', 'UTF-8'), $catalogue->get('bar', 'domain1'));
        $this->assertEquals(mb_convert_encoding('bär', 'ISO-8859-1', 'UTF-8'), $catalogue->get('foo', 'domain1'));
        $this->assertEquals(
            [
                'source' => 'foo',
                'notes' => [['content' => mb_convert_encoding('bäz', 'ISO-8859-1', 'UTF-8')]],
                'id' => '1',
                'file' => [
                    'original' => 'file.ext',
                ],
            ],
            $catalogue->getMetadata('foo', 'domain1')
        );
    }

    public function testTargetAttributesAreStoredCorrectly(): void
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/with-attributes.xlf', 'en', 'domain1');

        $metadata = $catalogue->getMetadata('foo', 'domain1');
        $this->assertEquals('translated', $metadata['target-attributes']['state']);
    }

    public function testLoadInvalidResource(): void
    {
        $this->expectException(InvalidResourceException::class);

        (new XliffFileLoader())->load(__DIR__.'/../Fixtures/resources.php', 'en', 'domain1');
    }

    public function testLoadResourceDoesNotValidate(): void
    {
        $this->expectException(InvalidResourceException::class);

        (new XliffFileLoader())->load(__DIR__.'/../Fixtures/non-valid.xlf', 'en', 'domain1');
    }

    public function testLoadNonExistingResource(): void
    {
        $this->expectException(NotFoundResourceException::class);

        (new XliffFileLoader())->load(__DIR__.'/../Fixtures/non-existing.xlf', 'en', 'domain1');
    }

    public function testLoadThrowsAnExceptionIfFileNotLocal(): void
    {
        $this->expectException(InvalidResourceException::class);

        (new XliffFileLoader())->load('http://example.com/resources.xlf', 'en', 'domain1');
    }

    public function testDocTypeIsNotAllowed(): void
    {
        $this->expectException(InvalidResourceException::class);
        $this->expectExceptionMessage('Document types are not allowed.');

        (new XliffFileLoader())->load(__DIR__.'/../Fixtures/withdoctype.xlf', 'en', 'domain1');
    }

    public function testParseEmptyFile(): void
    {
        $resource = __DIR__.'/../Fixtures/empty.xlf';

        $this->expectException(InvalidResourceException::class);
        $this->expectExceptionMessage(\sprintf('Unable to load "%s":', $resource));

        (new XliffFileLoader())->load($resource, 'en', 'domain1');
    }

    public function testLoadNotes(): void
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/withnote.xlf', 'en', 'domain1');

        $this->assertEquals(
            [
                'source' => 'foo',
                'notes' => [['priority' => 1, 'content' => 'foo']],
                'id' => '1',
                'file' => [
                    'original' => 'file.ext',
                ],
            ],
            $catalogue->getMetadata('foo', 'domain1')
        );
        // message without target
        $this->assertEquals(
            [
                'source' => 'extrasource',
                'notes' => [['content' => 'bar', 'from' => 'foo']],
                'id' => '2',
                'file' => [
                    'original' => 'file.ext',
                ],
            ],
            $catalogue->getMetadata('extra', 'domain1')
        );
        // message with empty target
        $this->assertEquals(
            [
                'source' => 'key',
                'notes' => [
                    ['content' => 'baz'],
                    ['priority' => 2, 'from' => 'bar', 'content' => 'qux'],
                ],
                'id' => '123',
                'file' => [
                    'original' => 'file.ext',
                ],
            ],
            $catalogue->getMetadata('key', 'domain1')
        );
    }

    public function testLoadVersion2(): void
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources-2.0.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
        $this->assertSame([], libxml_get_errors());

        $domains = $catalogue->all();
        $this->assertCount(3, $domains['domain1']);
        $this->assertContainsOnlyString($catalogue->all('domain1'));

        // target attributes
        $this->assertEquals(['target-attributes' => ['order' => 1]], $catalogue->getMetadata('bar', 'domain1'));
    }

    public function testLoadVersion2WithNoteMeta(): void
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources-notes-meta.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
        $this->assertSame([], libxml_get_errors());

        // test for "foo" metadata
        $this->assertTrue($catalogue->defines('foo', 'domain1'));
        $metadata = $catalogue->getMetadata('foo', 'domain1');
        $this->assertNotEmpty($metadata);
        $this->assertCount(3, $metadata['notes']);

        $this->assertEquals('state', $metadata['notes'][0]['category']);
        $this->assertEquals('new', $metadata['notes'][0]['content']);

        $this->assertEquals('approved', $metadata['notes'][1]['category']);
        $this->assertEquals('true', $metadata['notes'][1]['content']);

        $this->assertEquals('section', $metadata['notes'][2]['category']);
        $this->assertEquals('1', $metadata['notes'][2]['priority']);
        $this->assertEquals('user login', $metadata['notes'][2]['content']);

        // test for "baz" metadata
        $this->assertTrue($catalogue->defines('baz', 'domain1'));
        $metadata = $catalogue->getMetadata('baz', 'domain1');
        $this->assertNotEmpty($metadata);
        $this->assertCount(2, $metadata['notes']);

        $this->assertEquals('x', $metadata['notes'][0]['id']);
        $this->assertEquals('x_content', $metadata['notes'][0]['content']);

        $this->assertEquals('target', $metadata['notes'][1]['appliesTo']);
        $this->assertEquals('quality', $metadata['notes'][1]['category']);
        $this->assertEquals('Fuzzy', $metadata['notes'][1]['content']);
    }

    public function testLoadVersion2WithMultiSegmentUnit(): void
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources-2.0-multi-segment-unit.xlf';
        $catalog = $loader->load($resource, 'en', 'domain1');

        $this->assertSame('en', $catalog->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalog->getResources());
        $this->assertFalse(libxml_get_last_error());

        // test for "foo" metadata
        $this->assertTrue($catalog->defines('foo', 'domain1'));
        $metadata = $catalog->getMetadata('foo', 'domain1');
        $this->assertNotEmpty($metadata);
        $this->assertCount(1, $metadata['notes']);

        $this->assertSame('processed', $metadata['notes'][0]['category']);
        $this->assertSame('true', $metadata['notes'][0]['content']);

        // test for "bar" metadata
        $this->assertTrue($catalog->defines('bar', 'domain1'));
        $metadata = $catalog->getMetadata('bar', 'domain1');
        $this->assertNotEmpty($metadata);
        $this->assertCount(1, $metadata['notes']);

        $this->assertSame('processed', $metadata['notes'][0]['category']);
        $this->assertSame('true', $metadata['notes'][0]['content']);
    }

    public function testLoadWithMultipleFileNodes(): void
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/resources-multi-files.xlf', 'en', 'domain1');

        $this->assertEquals(
            [
                'source' => 'foo',
                'id' => '1',
                'file' => [
                    'original' => 'file.ext',
                ],
            ],
            $catalogue->getMetadata('foo', 'domain1')
        );
        $this->assertEquals(
            [
                'source' => 'test',
                'notes' => [['content' => 'note']],
                'id' => '4',
                'file' => [
                    'original' => 'otherfile.ext',
                ],
            ],
            $catalogue->getMetadata('test', 'domain1')
        );
    }

    public function testLoadVersion2WithName(): void
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/resources-2.0-name.xlf', 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo', 'qux' => 'qux source'], $catalogue->all('domain1'));
    }

    public function testLoadVersion2WithSegmentAttributes(): void
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources-2.0-segment-attributes.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        // test for "foo" metadata
        $this->assertTrue($catalogue->defines('foo', 'domain1'));
        $metadata = $catalogue->getMetadata('foo', 'domain1');
        $this->assertNotEmpty($metadata);
        $this->assertCount(1, $metadata['segment-attributes']);
        $this->assertArrayHasKey('state', $metadata['segment-attributes']);
        $this->assertSame('translated', $metadata['segment-attributes']['state']);

        // test for "key" metadata
        $this->assertTrue($catalogue->defines('key', 'domain1'));
        $metadata = $catalogue->getMetadata('key', 'domain1');
        $this->assertNotEmpty($metadata);
        $this->assertCount(2, $metadata['segment-attributes']);
        $this->assertArrayHasKey('state', $metadata['segment-attributes']);
        $this->assertSame('translated', $metadata['segment-attributes']['state']);
        $this->assertArrayHasKey('subState', $metadata['segment-attributes']);
        $this->assertSame('My Value', $metadata['segment-attributes']['subState']);
    }
}
