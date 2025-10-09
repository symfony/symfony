<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;

class XliffFileDumperTest extends TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
            'key' => '',
            'key.with.cdata' => '<source> & <target>',
        ]);
        $catalogue->setMetadata('foo', ['notes' => [['priority' => 1, 'from' => 'bar', 'content' => 'baz']]]);
        $catalogue->setMetadata('key', ['notes' => [['content' => 'baz'], ['content' => 'qux']]]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-clean.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR'])
        );
    }

    public function testFormatCatalogueXliff2()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->setCatalogueMetadata('key', 'value');
        $catalogue->add([
            'foo' => 'bar',
            'key' => '',
            'key.with.cdata' => '<source> & <target>',
            'translation.key.that.is.longer.than.eighty.characters.should.not.have.name.attribute' => 'value',
        ]);
        $catalogue->setMetadata('key', ['target-attributes' => ['order' => 1]]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-2.0-clean.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR', 'xliff_version' => '2.0'])
        );
    }

    public function testFormatIcuCatalogueXliff2()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
        ], 'messages'.MessageCatalogue::INTL_DOMAIN_SUFFIX);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-2.0+intl-icu.xlf',
            $dumper->formatCatalogue($catalogue, 'messages'.MessageCatalogue::INTL_DOMAIN_SUFFIX, ['default_locale' => 'fr_FR', 'xliff_version' => '2.0'])
        );
    }

    public function testFormatCatalogueWithCustomToolInfo()
    {
        $options = [
            'default_locale' => 'en_US',
            'tool_info' => ['tool-id' => 'foo', 'tool-name' => 'foo', 'tool-version' => '0.0', 'tool-company' => 'Foo'],
        ];

        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add(['foo' => 'bar']);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-tool-info.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', $options)
        );
    }

    public function testFormatCatalogueWithTargetAttributesMetadata()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
        ]);
        $catalogue->setMetadata('foo', ['target-attributes' => ['state' => 'needs-translation']]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-target-attributes.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR'])
        );
    }

    public function testFormatCatalogueWithNotesMetadata()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
            'baz' => 'biz',
        ]);
        $catalogue->setMetadata('foo', ['notes' => [
            ['category' => 'state', 'content' => 'new'],
            ['category' => 'approved', 'content' => 'true'],
            ['category' => 'section', 'content' => 'user login', 'priority' => '1'],
        ]]);
        $catalogue->setMetadata('baz', ['notes' => [
            ['id' => 'x', 'content' => 'x_content'],
            ['appliesTo' => 'target', 'category' => 'quality', 'content' => 'Fuzzy'],
        ]]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-notes-meta.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR', 'xliff_version' => '2.0'])
        );
    }

    public function testDumpCatalogueWithXliffExtension()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
            'key' => '',
            'key.with.cdata' => '<source> & <target>',
        ]);
        $catalogue->setMetadata('foo', ['notes' => [['priority' => 1, 'from' => 'bar', 'content' => 'baz']]]);
        $catalogue->setMetadata('key', ['notes' => [['content' => 'baz'], ['content' => 'qux']]]);

        $dumper = new XliffFileDumper('xliff');

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-clean.xliff',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR'])
        );
    }

    public function testEmptyMetadataNotes()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'empty' => 'notes',
            'full' => 'notes',
        ]);
        $catalogue->setMetadata('empty', ['notes' => []]);
        $catalogue->setMetadata('full', ['notes' => [['category' => 'file-source', 'priority' => 1, 'content' => 'test/path/to/translation/Example.1.html.twig:27']]]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-2.0-empty-notes.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR', 'xliff_version' => '2.0'])
        );
    }

    public function testFormatCatalogueXliff2WithSegmentAttributes()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add([
            'foo' => 'bar',
            'key' => '',
        ]);
        $catalogue->setMetadata('foo', ['segment-attributes' => ['state' => 'translated']]);
        $catalogue->setMetadata('key', ['segment-attributes' => ['state' => 'translated', 'subState' => 'My Value']]);

        $dumper = new XliffFileDumper();

        $this->assertStringEqualsFile(
            __DIR__.'/../Fixtures/resources-2.0-segment-attributes.xlf',
            $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'fr_FR', 'xliff_version' => '2.0'])
        );
    }

    public function testFormatCatalogueWithSourceMetadata()
    {
        $catalogue = new MessageCatalogue('fr');
        $catalogue->add([
            'navbar.home' => 'Accueil',
            'navbar.about' => 'À propos',
        ]);
        $catalogue->setMetadata('navbar.home', ['source' => 'Home']);
        $catalogue->setMetadata('navbar.about', ['source' => 'About']);

        $dumper = new XliffFileDumper();
        $output = $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'en']);

        $this->assertStringContainsString('<source>Home</source>', $output);
        $this->assertStringContainsString('<source>About</source>', $output);
        $this->assertStringContainsString('<target>Accueil</target>', $output);
        $this->assertStringContainsString('<target>À propos</target>', $output);
        $this->assertStringContainsString('resname="navbar.home"', $output);
        $this->assertStringContainsString('resname="navbar.about"', $output);
    }

    public function testFormatCatalogueXliff2WithSourceMetadata()
    {
        $catalogue = new MessageCatalogue('fr');
        $catalogue->add([
            'navbar.home' => 'Accueil',
            'navbar.about' => 'À propos',
        ]);
        $catalogue->setMetadata('navbar.home', ['source' => 'Home']);
        $catalogue->setMetadata('navbar.about', ['source' => 'About']);

        $dumper = new XliffFileDumper();
        $output = $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'en', 'xliff_version' => '2.0']);

        $this->assertStringContainsString('<source>Home</source>', $output);
        $this->assertStringContainsString('<source>About</source>', $output);
        $this->assertStringContainsString('<target>Accueil</target>', $output);
        $this->assertStringContainsString('<target>À propos</target>', $output);
        $this->assertStringContainsString('name="navbar.home"', $output);
        $this->assertStringContainsString('name="navbar.about"', $output);
    }

    public function testFormatCatalogueWithoutSourceMetadataFallsBackToKey()
    {
        $catalogue = new MessageCatalogue('fr');
        $catalogue->add([
            'navbar.home' => 'Accueil',
        ]);

        $dumper = new XliffFileDumper();
        $output = $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'en']);

        $this->assertStringContainsString('<source>navbar.home</source>', $output);
        $this->assertStringContainsString('resname="navbar.home"', $output);
    }

    public function testFormatCatalogueXliff2KeepsTheKeyWhenItIsTooLongForTheNameAttribute()
    {
        $key = str_repeat('a', 101);

        $catalogue = new MessageCatalogue('fr');
        $catalogue->add([$key => 'Accueil']);
        $catalogue->setMetadata($key, ['source' => 'Home']);

        $dumper = new XliffFileDumper();
        $output = $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'en', 'xliff_version' => '2.0']);

        $this->assertStringNotContainsString('name=', $output);
        $this->assertStringContainsString('<source>'.$key.'</source>', $output);

        $reloaded = new XliffFileLoader()->load($output, 'fr', 'messages');
        $this->assertSame([$key], array_keys($reloaded->all('messages')));
    }

    public function testFormatCatalogueIgnoresNonStringSourceMetadata()
    {
        $catalogue = new MessageCatalogue('fr');
        $catalogue->add(['navbar.home' => 'Accueil']);
        $catalogue->setMetadata('navbar.home', ['source' => ['not', 'a', 'string']]);

        $dumper = new XliffFileDumper();
        $output = $dumper->formatCatalogue($catalogue, 'messages', ['default_locale' => 'en']);

        $this->assertStringContainsString('<source>navbar.home</source>', $output);
    }

    public function testSourceSurvivesALoadDumpRoundTrip()
    {
        $xliff = <<<'XLIFF'
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="fr" datatype="plaintext" original="file.ext">
                <body>
                  <trans-unit id="1" resname="navbar.home">
                    <source>Home</source>
                    <target>Accueil</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>
            XLIFF;

        $catalogue = new XliffFileLoader()->load($xliff, 'fr', 'messages');

        $this->assertSame(['navbar.home' => 'Accueil'], $catalogue->all('messages'));

        $output = new XliffFileDumper()->formatCatalogue($catalogue, 'messages', ['default_locale' => 'en']);

        $this->assertStringContainsString('resname="navbar.home"', $output);
        $this->assertStringContainsString('<source>Home</source>', $output);
    }
}
