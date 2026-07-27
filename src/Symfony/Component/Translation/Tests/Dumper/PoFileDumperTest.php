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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Dumper\PoFileDumper;
use Symfony\Component\Translation\MessageCatalogue;

class PoFileDumperTest extends TestCase
{
    public function testFormatCatalogue()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar', 'bar' => 'foo', 'foo_bar' => 'foobar', 'bar_foo' => 'barfoo']);
        $catalogue->setMetadata('foo_bar', [
            'comments' => [
                'Comment 1',
                'Comment 2',
            ],
            'flags' => [
                'fuzzy',
                'another',
            ],
            'sources' => [
                'src/file_1',
                'src/file_2:50',
            ],
        ]);
        $catalogue->setMetadata('bar_foo', [
            'comments' => 'Comment',
            'flags' => 'fuzzy',
            'sources' => 'src/file_1',
        ]);

        $dumper = new PoFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../Fixtures/resources.po', $dumper->formatCatalogue($catalogue, 'messages'));
    }

    public function testDumpIntlDomainKeepsPipes()
    {
        $id = '{{subject}} must not be an instance of {{class|quote}}';
        $catalogue = new MessageCatalogue('en');
        $catalogue->set($id, $id, 'messages'.MessageCatalogue::INTL_DOMAIN_SUFFIX);

        $dumper = new PoFileDumper();
        $dump = $dumper->formatCatalogue($catalogue, 'messages'.MessageCatalogue::INTL_DOMAIN_SUFFIX);

        $this->assertStringContainsString(\sprintf('msgid "%s"', $id), $dump);
        $this->assertStringNotContainsString('msgid_plural', $dump);
    }

    public function testDumpContext()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar']);
        $catalogue->setMetadata('foo', ['context' => 'menu']);

        $dumper = new PoFileDumper();

        $expected = <<<'EOF'
            msgid ""
            msgstr ""
            "Content-Type: text/plain; charset=UTF-8\n"
            "Content-Transfer-Encoding: 8bit\n"
            "Language: en\n"
            "Plural-Forms: nplurals=2; plural=(n != 1);\n"

            msgctxt "menu"
            msgid "foo"
            msgstr "bar"

            EOF;

        $this->assertSame($expected, $dumper->formatCatalogue($catalogue, 'messages'));
    }

    public function testDumpPlurals()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add([
            'foo|foos' => 'bar|bars',
            '{0} no foos|one foo|%count% foos' => '{0} no bars|one bar|%count% bars',
        ]);

        $dumper = new PoFileDumper();

        $this->assertStringEqualsFile(__DIR__.'/../Fixtures/plurals.po', $dumper->formatCatalogue($catalogue, 'messages'));
    }

    #[DataProvider('pluralFormsProvider')]
    public function testPluralFormsHeaderIsDerivedFromLocale(string $locale, string $expectedPluralForms)
    {
        $catalogue = new MessageCatalogue($locale);
        $catalogue->add(['foo' => 'bar']);

        $output = (new PoFileDumper())->formatCatalogue($catalogue, 'messages');

        $this->assertStringContainsString('"Plural-Forms: '.$expectedPluralForms.'\n"', $output);
    }

    /**
     * One representative per plural-rule group of TranslatorTrait::getPluralizationRule(), so every
     * branch of the mapping is pinned. Locales within the same group share the same formula.
     */
    public static function pluralFormsProvider(): array
    {
        return [
            'english-like (two forms)' => ['en', 'nplurals=2; plural=(n != 1);'],
            'french-like (two forms, n > 1)' => ['fr', 'nplurals=2; plural=(n > 1);'],
            'russian-like (three forms)' => ['ru', 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);'],
            'czech-like (three forms)' => ['cs', 'nplurals=3; plural=(n==1 ? 0 : n>=2 && n<=4 ? 1 : 2);'],
            'irish (three forms)' => ['ga', 'nplurals=3; plural=(n==1 ? 0 : n==2 ? 1 : 2);'],
            'lithuanian (three forms)' => ['lt', 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 2);'],
            'slovenian (four forms)' => ['sl', 'nplurals=4; plural=(n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3);'],
            'macedonian (two forms)' => ['mk', 'nplurals=2; plural=(n%10==1 ? 0 : 1);'],
            'maltese (four forms)' => ['mt', 'nplurals=4; plural=(n==1 ? 0 : n==0 || (n%100>1 && n%100<11) ? 1 : n%100>10 && n%100<20 ? 2 : 3);'],
            'latvian (three forms)' => ['lv', 'nplurals=3; plural=(n==0 ? 0 : n%10==1 && n%100!=11 ? 1 : 2);'],
            'polish (three forms)' => ['pl', 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2);'],
            'welsh (four forms)' => ['cy', 'nplurals=4; plural=(n==1 ? 0 : n==2 ? 1 : n==8 || n==11 ? 2 : 3);'],
            'romanian (three forms)' => ['ro', 'nplurals=3; plural=(n==1 ? 0 : n==0 || (n%100>0 && n%100<20) ? 1 : 2);'],
            'arabic (six forms)' => ['ar', 'nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 && n%100<=99 ? 4 : 5);'],
            'japanese (single form default)' => ['ja', 'nplurals=1; plural=0;'],
            'french region is stripped to its prefix' => ['fr_FR', 'nplurals=2; plural=(n > 1);'],
            'pt_BR is not stripped to pt' => ['pt_BR', 'nplurals=2; plural=(n > 1);'],
            'en_US_POSIX is kept as-is' => ['en_US_POSIX', 'nplurals=2; plural=(n != 1);'],
            'unknown locale falls back to a single form' => ['xx', 'nplurals=1; plural=0;'],
        ];
    }
}
