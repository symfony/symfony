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
    public function testLoadFile()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        self::assertEquals('en', $catalogue->getLocale());
        self::assertEquals([new FileResource($resource)], $catalogue->getResources());
        self::assertSame([], libxml_get_errors());
        self::assertContainsOnly('string', $catalogue->all('domain1'));
    }

    public function testLoadRawXliff()
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
        <target>with</target>
        <note>note</note>
      </trans-unit>
    </body>
  </file>
</xliff>
XLIFF;

        $catalogue = $loader->load($resource, 'en', 'domain1');

        self::assertEquals('en', $catalogue->getLocale());
        self::assertSame([], libxml_get_errors());
        self::assertContainsOnly('string', $catalogue->all('domain1'));
    }

    public function testLoadWithInternalErrorsEnabled()
    {
        $internalErrors = libxml_use_internal_errors(true);

        self::assertSame([], libxml_get_errors());

        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        self::assertEquals('en', $catalogue->getLocale());
        self::assertEquals([new FileResource($resource)], $catalogue->getResources());
        self::assertSame([], libxml_get_errors());

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
    }

    public function testLoadWithExternalEntitiesDisabled()
    {
        if (\LIBXML_VERSION < 20900) {
            $disableEntities = libxml_disable_entity_loader(true);
        }

        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        if (\LIBXML_VERSION < 20900) {
            libxml_disable_entity_loader($disableEntities);
        }

        self::assertEquals('en', $catalogue->getLocale());
        self::assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadWithResname()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resname.xlf', 'en', 'domain1');

        self::assertEquals(['foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo', 'qux' => 'qux source'], $catalogue->all('domain1'));
    }

    public function testIncompleteResource()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resources.xlf', 'en', 'domain1');

        self::assertEquals(['foo' => 'bar', 'extra' => 'extra', 'key' => '', 'test' => 'with'], $catalogue->all('domain1'));
    }

    public function testEncoding()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/encoding.xlf', 'en', 'domain1');

        self::assertEquals(mb_convert_encoding('föö', 'ISO-8859-1', 'UTF-8'), $catalogue->get('bar', 'domain1'));
        self::assertEquals(mb_convert_encoding('bär', 'ISO-8859-1', 'UTF-8'), $catalogue->get('foo', 'domain1'));
        self::assertEquals([
            'source' => 'foo',
            'notes' => [['content' => mb_convert_encoding('bäz', 'ISO-8859-1', 'UTF-8')]],
            'id' => '1',
            'file' => [
                'original' => 'file.ext',
            ],
        ], $catalogue->getMetadata('foo', 'domain1'));
    }

    public function testTargetAttributesAreStoredCorrectly()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/with-attributes.xlf', 'en', 'domain1');

        $metadata = $catalogue->getMetadata('foo', 'domain1');
        self::assertEquals('translated', $metadata['target-attributes']['state']);
    }

    public function testLoadInvalidResource()
    {
        self::expectException(InvalidResourceException::class);
        $loader = new XliffFileLoader();
        $loader->load(__DIR__.'/../fixtures/resources.php', 'en', 'domain1');
    }

    public function testLoadResourceDoesNotValidate()
    {
        self::expectException(InvalidResourceException::class);
        $loader = new XliffFileLoader();
        $loader->load(__DIR__.'/../fixtures/non-valid.xlf', 'en', 'domain1');
    }

    public function testLoadNonExistingResource()
    {
        self::expectException(NotFoundResourceException::class);
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/non-existing.xlf';
        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadThrowsAnExceptionIfFileNotLocal()
    {
        self::expectException(InvalidResourceException::class);
        $loader = new XliffFileLoader();
        $resource = 'http://example.com/resources.xlf';
        $loader->load($resource, 'en', 'domain1');
    }

    public function testDocTypeIsNotAllowed()
    {
        self::expectException(InvalidResourceException::class);
        self::expectExceptionMessage('Document types are not allowed.');
        $loader = new XliffFileLoader();
        $loader->load(__DIR__.'/../fixtures/withdoctype.xlf', 'en', 'domain1');
    }

    public function testParseEmptyFile()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/empty.xlf';

        self::expectException(InvalidResourceException::class);
        self::expectExceptionMessage(sprintf('Unable to load "%s":', $resource));

        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadNotes()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/withnote.xlf', 'en', 'domain1');

        self::assertEquals([
            'source' => 'foo',
            'notes' => [['priority' => 1, 'content' => 'foo']],
            'id' => '1',
            'file' => [
                'original' => 'file.ext',
            ],
        ], $catalogue->getMetadata('foo', 'domain1'));
        // message without target
        self::assertEquals([
            'source' => 'extrasource',
            'notes' => [['content' => 'bar', 'from' => 'foo']],
            'id' => '2',
            'file' => [
                'original' => 'file.ext',
            ],
        ], $catalogue->getMetadata('extra', 'domain1'));
        // message with empty target
        self::assertEquals([
            'source' => 'key',
            'notes' => [
                ['content' => 'baz'],
                ['priority' => 2, 'from' => 'bar', 'content' => 'qux'],
            ],
            'id' => '123',
            'file' => [
                'original' => 'file.ext',
            ],
        ], $catalogue->getMetadata('key', 'domain1'));
    }

    public function testLoadVersion2()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources-2.0.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        self::assertEquals('en', $catalogue->getLocale());
        self::assertEquals([new FileResource($resource)], $catalogue->getResources());
        self::assertSame([], libxml_get_errors());

        $domains = $catalogue->all();
        self::assertCount(3, $domains['domain1']);
        self::assertContainsOnly('string', $catalogue->all('domain1'));

        // target attributes
        self::assertEquals(['target-attributes' => ['order' => 1]], $catalogue->getMetadata('bar', 'domain1'));
    }

    public function testLoadVersion2WithNoteMeta()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources-notes-meta.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        self::assertEquals('en', $catalogue->getLocale());
        self::assertEquals([new FileResource($resource)], $catalogue->getResources());
        self::assertSame([], libxml_get_errors());

        // test for "foo" metadata
        self::assertTrue($catalogue->defines('foo', 'domain1'));
        $metadata = $catalogue->getMetadata('foo', 'domain1');
        self::assertNotEmpty($metadata);
        self::assertCount(3, $metadata['notes']);

        self::assertEquals('state', $metadata['notes'][0]['category']);
        self::assertEquals('new', $metadata['notes'][0]['content']);

        self::assertEquals('approved', $metadata['notes'][1]['category']);
        self::assertEquals('true', $metadata['notes'][1]['content']);

        self::assertEquals('section', $metadata['notes'][2]['category']);
        self::assertEquals('1', $metadata['notes'][2]['priority']);
        self::assertEquals('user login', $metadata['notes'][2]['content']);

        // test for "baz" metadata
        self::assertTrue($catalogue->defines('baz', 'domain1'));
        $metadata = $catalogue->getMetadata('baz', 'domain1');
        self::assertNotEmpty($metadata);
        self::assertCount(2, $metadata['notes']);

        self::assertEquals('x', $metadata['notes'][0]['id']);
        self::assertEquals('x_content', $metadata['notes'][0]['content']);

        self::assertEquals('target', $metadata['notes'][1]['appliesTo']);
        self::assertEquals('quality', $metadata['notes'][1]['category']);
        self::assertEquals('Fuzzy', $metadata['notes'][1]['content']);
    }

    public function testLoadVersion2WithMultiSegmentUnit()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources-2.0-multi-segment-unit.xlf';
        $catalog = $loader->load($resource, 'en', 'domain1');

        self::assertSame('en', $catalog->getLocale());
        self::assertEquals([new FileResource($resource)], $catalog->getResources());
        self::assertFalse(libxml_get_last_error());

        // test for "foo" metadata
        self::assertTrue($catalog->defines('foo', 'domain1'));
        $metadata = $catalog->getMetadata('foo', 'domain1');
        self::assertNotEmpty($metadata);
        self::assertCount(1, $metadata['notes']);

        self::assertSame('processed', $metadata['notes'][0]['category']);
        self::assertSame('true', $metadata['notes'][0]['content']);

        // test for "bar" metadata
        self::assertTrue($catalog->defines('bar', 'domain1'));
        $metadata = $catalog->getMetadata('bar', 'domain1');
        self::assertNotEmpty($metadata);
        self::assertCount(1, $metadata['notes']);

        self::assertSame('processed', $metadata['notes'][0]['category']);
        self::assertSame('true', $metadata['notes'][0]['content']);
    }

    public function testLoadWithMultipleFileNodes()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resources-multi-files.xlf', 'en', 'domain1');

        self::assertEquals([
            'source' => 'foo',
            'id' => '1',
            'file' => [
                'original' => 'file.ext',
            ],
        ], $catalogue->getMetadata('foo', 'domain1'));
        self::assertEquals([
            'source' => 'test',
            'notes' => [['content' => 'note']],
            'id' => '4',
            'file' => [
                'original' => 'otherfile.ext',
            ],
        ], $catalogue->getMetadata('test', 'domain1'));
    }

    public function testLoadVersion2WithName()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resources-2.0-name.xlf', 'en', 'domain1');

        self::assertEquals(['foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo', 'qux' => 'qux source'], $catalogue->all('domain1'));
    }
}
