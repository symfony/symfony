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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogueInterface;

class XliffFileLoaderTest extends TestCase
{
    public function testLoadFile()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
        $this->assertSame([], libxml_get_errors());
        $this->assertContainsOnlyString($catalogue->all('domain1'));
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

    public function testLoadWithInternalErrorsEnabled()
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

    public function testLoadWithExternalEntitiesDisabled()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadWithResname()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/resname.xlf', 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo', 'qux' => 'qux source'], $catalogue->all('domain1'));
    }

    public function testIncompleteResource()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/resources.xlf', 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar', 'extra' => 'extra', 'key' => '', 'test' => 'with'], $catalogue->all('domain1'));
    }

    public function testEncoding()
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

    public function testTargetAttributesAreStoredCorrectly()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/with-attributes.xlf', 'en', 'domain1');

        $metadata = $catalogue->getMetadata('foo', 'domain1');
        $this->assertEquals('translated', $metadata['target-attributes']['state']);
    }

    public function testLoadInvalidResource()
    {
        $this->expectException(InvalidResourceException::class);

        (new XliffFileLoader())->load(__DIR__.'/../Fixtures/resources.php', 'en', 'domain1');
    }

    public function testLoadResourceDoesNotValidate()
    {
        $this->expectException(InvalidResourceException::class);

        (new XliffFileLoader())->load(__DIR__.'/../Fixtures/non-valid.xlf', 'en', 'domain1');
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);

        (new XliffFileLoader())->load(__DIR__.'/../Fixtures/non-existing.xlf', 'en', 'domain1');
    }

    public function testLoadThrowsAnExceptionIfFileNotLocal()
    {
        $this->expectException(InvalidResourceException::class);

        (new XliffFileLoader())->load('http://example.com/resources.xlf', 'en', 'domain1');
    }

    public function testDocTypeIsNotAllowed()
    {
        $this->expectException(InvalidResourceException::class);
        $this->expectExceptionMessage('Document types are not allowed.');

        (new XliffFileLoader())->load(__DIR__.'/../Fixtures/withdoctype.xlf', 'en', 'domain1');
    }

    public function testParseEmptyFile()
    {
        $resource = __DIR__.'/../Fixtures/empty.xlf';

        $this->expectException(InvalidResourceException::class);
        $this->expectExceptionMessage(\sprintf('Unable to load "%s":', $resource));

        (new XliffFileLoader())->load($resource, 'en', 'domain1');
    }

    public function testLoadNotes()
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

    public function testLoadVersion2()
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

    public function testLoadVersion21()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources-2.1.xlf';
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

    public function testLoadVersion22()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../Fixtures/resources-2.2.xlf';
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

    public function testLoadVersion2WithNoteMeta()
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

    public function testLoadVersion2WithMultiSegmentUnit()
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

    public function testLoadWithMultipleFileNodes()
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

    public function testLoadVersion2WithName()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../Fixtures/resources-2.0-name.xlf', 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo', 'qux' => 'qux source'], $catalogue->all('domain1'));
    }

    public function testLoadVersion2WithSegmentAttributes()
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

    public function testLoadVersion22WithPgsPlural()
    {
        $catalogue = new XliffFileLoader()->load(__DIR__.'/../Fixtures/resources-2.2-pgs-plural.xlf', 'fr', 'domain1');

        $intlDomain = 'domain1'.MessageCatalogueInterface::INTL_DOMAIN_SUFFIX;

        $this->assertTrue($catalogue->defines('file_deleted', $intlDomain));
        $this->assertSame(
            '{file_count, plural, =0 {Vous n\'avez supprimé aucun fichier.} =1 {Vous avez supprimé un fichier.} other {Vous avez supprimé # fichiers.}}',
            $catalogue->get('file_deleted', $intlDomain)
        );

        $this->assertSame('plural:file_count', $catalogue->getMetadata('file_deleted', $intlDomain)['pgs-switch']);
    }

    public function testLoadVersion22WithPgsGender()
    {
        $catalogue = new XliffFileLoader()->load(__DIR__.'/../Fixtures/resources-2.2-pgs-gender.xlf', 'fr', 'domain1');

        $intlDomain = 'domain1'.MessageCatalogueInterface::INTL_DOMAIN_SUFFIX;

        $this->assertTrue($catalogue->defines('party_invite', $intlDomain));
        $this->assertSame(
            '{host_gender, select, feminine {Vous êtes invité à sa fête} masculine {Vous êtes invité à sa fête} other {Vous êtes invité à leur fête}}',
            $catalogue->get('party_invite', $intlDomain)
        );
    }

    public function testLoadVersion22WithPgsCombined()
    {
        $catalogue = new XliffFileLoader()->load(__DIR__.'/../Fixtures/resources-2.2-pgs-combined.xlf', 'fr', 'domain1');

        $intlDomain = 'domain1'.MessageCatalogueInterface::INTL_DOMAIN_SUFFIX;

        $this->assertTrue($catalogue->defines('party_host', $intlDomain));

        $expected = <<<ICU
            {host_gender, select, feminine {{guest_count, plural, =0 {{host_name} n'a invité personne à sa fête.} =1 {{host_name} a invité un convive à sa fête.} other {{host_name} a invité # convives à sa fête.}}} masculine {{guest_count, plural, =0 {{host_name} n'a invité personne à sa fête.} =1 {{host_name} a invité un convive à sa fête.} other {{host_name} a invité # convives à sa fête.}}} other {{guest_count, plural, =0 {{host_name} n'a invité personne à leur fête.} =1 {{host_name} a invité un convive à leur fête.} other {{host_name} a invité # convives à leur fête.}}}}
            ICU;

        $this->assertSame($expected, $catalogue->get('party_host', $intlDomain));
    }

    public static function provideMalformedPgsSwitchValues(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'missing variable' => ['plural'];
        yield 'trailing colon' => ['plural:'];
        yield 'leading colon' => [':file_count'];
    }

    #[DataProvider('provideMalformedPgsSwitchValues')]
    public function testLoadVersion22RejectsMalformedPgsSwitch(string $pgsSwitch)
    {
        $xml = <<<XLIFF
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" xmlns:pgs="urn:oasis:names:tc:xliff:pgs:1.0"
                   version="2.2" srcLang="en" trgLang="fr">
                <file id="f1">
                    <unit id="tu1" name="file_deleted" pgs:switch="$pgsSwitch">
                        <segment id="seg1" pgs:case="other">
                            <source>You deleted files.</source>
                            <target>Vous avez supprimé des fichiers.</target>
                        </segment>
                    </unit>
                </file>
            </xliff>
            XLIFF;

        $this->expectException(InvalidResourceException::class);

        new XliffFileLoader()->load($xml, 'fr', 'domain1');
    }

    public function testExtractPgsSegmentPreservesInlineElements()
    {
        $xml = <<<XLIFF
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" xmlns:pgs="urn:oasis:names:tc:xliff:pgs:1.0"
                   version="2.2" srcLang="en" trgLang="fr">
                <file id="f1">
                    <unit id="tu1" name="greeting" pgs:switch="select:audience">
                        <segment id="seg1" pgs:case="formal">
                            <source>Hello there</source>
                            <target>Bonjour <pc id="1">Madame</pc> <mrk id="m1" type="term">Dupont</mrk><cp hex="00A0"/>!</target>
                        </segment>
                    </unit>
                </file>
            </xliff>
            XLIFF;

        $catalogue = new XliffFileLoader()->load($xml, 'fr', 'domain1');
        $intlDomain = 'domain1'.MessageCatalogueInterface::INTL_DOMAIN_SUFFIX;

        $this->assertSame(
            "{audience, select, formal {Bonjour Madame Dupont\u{00A0}!}}",
            $catalogue->get('greeting', $intlDomain)
        );
    }

    public function testExtractPgsSegmentSkipsInvalidCpCodepoints()
    {
        $xml = <<<XLIFF
            <?xml version="1.0" encoding="UTF-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" xmlns:pgs="urn:oasis:names:tc:xliff:pgs:1.0"
                   version="2.2" srcLang="en" trgLang="fr">
                <file id="f1">
                    <unit id="tu1" name="greeting" pgs:switch="select:audience">
                        <segment id="seg1" pgs:case="formal">
                            <source>Hello there</source>
                            <target>Bonjour<cp hex="D800"/><cp hex="DFFF"/>!</target>
                        </segment>
                    </unit>
                </file>
            </xliff>
            XLIFF;

        $errors = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$errors) {
            $errors[] = $errstr;

            return true;
        });

        try {
            $catalogue = new XliffFileLoader()->load($xml, 'fr', 'domain1');
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $errors);
        $intlDomain = 'domain1'.MessageCatalogueInterface::INTL_DOMAIN_SUFFIX;
        $this->assertSame(
            '{audience, select, formal {Bonjour!}}',
            $catalogue->get('greeting', $intlDomain)
        );
    }
}
