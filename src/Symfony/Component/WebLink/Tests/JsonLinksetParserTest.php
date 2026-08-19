<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\WebLink\Exception\InvalidArgumentException;
use Symfony\Component\WebLink\JsonLinksetParser;
use Symfony\Component\WebLink\JsonLinksetSerializer;
use Symfony\Component\WebLink\Link;

class JsonLinksetParserTest extends TestCase
{
    private JsonLinksetParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonLinksetParser();
    }

    public function testParseEmptyLinkset()
    {
        self::assertSame([], $this->parser->parse('{"linkset": []}')->getLinks());
    }

    public function testParseSingleLink()
    {
        $links = $this->parser->parse(<<<'JSON'
            {"linkset": [
                {"anchor": "https://example.net/bar", "next": [{"href": "https://example.com/foo"}]}
            ]}
            JSON)->getLinks();

        self::assertCount(1, $links);
        self::assertSame(['next'], $links[0]->getRels());
        self::assertSame('https://example.com/foo', $links[0]->getHref());
        self::assertSame(['anchor' => 'https://example.net/bar'], $links[0]->getAttributes());
    }

    public function testParseWithoutAnchor()
    {
        $links = $this->parser->parse('{"linkset": [{"next": [{"href": "https://example.com/foo"}]}]}')->getLinks();

        self::assertCount(1, $links);
        self::assertSame([], $links[0]->getAttributes());
    }

    public function testParseSeveralContextsAndTargets()
    {
        $links = $this->parser->parse(<<<'JSON'
            {"linkset": [
                {"anchor": "https://example.net/bar", "item": [
                    {"href": "https://example.com/foo1"},
                    {"href": "https://example.com/foo2"}
                ]},
                {"anchor": "https://example.net/boo", "https://example.com/relations/baz": [
                    {"href": "https://example.com/foo3"}
                ]}
            ]}
            JSON)->getLinks();

        self::assertCount(3, $links);
        self::assertSame(['item'], $links[0]->getRels());
        self::assertSame('https://example.com/foo1', $links[0]->getHref());
        self::assertSame(['item'], $links[1]->getRels());
        self::assertSame('https://example.com/foo2', $links[1]->getHref());
        self::assertSame(['https://example.com/relations/baz'], $links[2]->getRels());
        self::assertSame(['anchor' => 'https://example.net/boo'], $links[2]->getAttributes());
    }

    public function testParseTargetAttributesDefinedByWebLinking()
    {
        $links = $this->parser->parse(<<<'JSON'
            {"linkset": [
                {"next": [{
                    "href": "https://example.com/foo",
                    "type": "text/html",
                    "media": "screen",
                    "hreflang": ["en", "de"]
                }]}
            ]}
            JSON)->getLinks();

        self::assertSame([
            'type' => 'text/html',
            'media' => 'screen',
            'hreflang' => ['en', 'de'],
        ], $links[0]->getAttributes());
    }

    public function testParseUnwrapsSingleValuedArrays()
    {
        $links = $this->parser->parse('{"linkset": [{"next": [{"href": "/foo", "hreflang": ["en"], "foo": ["foovalue"]}]}]}')->getLinks();

        self::assertSame(['hreflang' => 'en', 'foo' => 'foovalue'], $links[0]->getAttributes());
    }

    public function testParseInternationalizedTargetAttributes()
    {
        $links = $this->parser->parse(<<<'JSON'
            {"linkset": [
                {"next": [{
                    "href": "https://example.com/foo",
                    "title": "Next chapter",
                    "title*": [{"value": "nächstes Kapitel", "language": "de"}],
                    "baz*": [{"value": "bazvalue"}]
                }]}
            ]}
            JSON)->getLinks();

        self::assertSame([
            'title' => 'Next chapter',
            'title*' => "UTF-8'de'n%C3%A4chstes%20Kapitel",
            'baz*' => "UTF-8''bazvalue",
        ], $links[0]->getAttributes());
    }

    public function testParseRepeatedInternationalizedTargetAttributes()
    {
        $links = $this->parser->parse('{"linkset": [{"next": [{"href": "/foo", "title*": [{"value": "a", "language": "en"}, {"value": "b", "language": "fr"}]}]}]}')->getLinks();

        self::assertSame(['title*' => ["UTF-8'en'a", "UTF-8'fr'b"]], $links[0]->getAttributes());
    }

    public function testParseCastsScalarAttributesToStrings()
    {
        $links = $this->parser->parse('{"linkset": [{"next": [{"href": "/foo", "pr": [0.7], "nopush": [true]}]}]}')->getLinks();

        self::assertSame(['pr' => '0.7', 'nopush' => '1'], $links[0]->getAttributes());
    }

    public function testParseIgnoresExtensionsThatAreNotTargetAttributes()
    {
        $links = $this->parser->parse('{"linkset": [{"next": [{"href": "/foo"}]}], "meta": {"generated": "today"}}')->getLinks();

        self::assertCount(1, $links);
        self::assertSame('/foo', $links[0]->getHref());
    }

    public function testRoundTrip()
    {
        $links = [
            (new Link('next', 'https://example.com/foo'))
                ->withAttribute('anchor', 'https://example.net/bar')
                ->withAttribute('type', 'text/html')
                ->withAttribute('hreflang', ['en', 'de'])
                ->withAttribute('title', 'Next chapter')
                ->withAttribute('bar', ['barone', 'bartwo']),
        ];

        $document = (new JsonLinksetSerializer())->serialize($links);

        self::assertEquals($links, $this->parser->parse($document)->getLinks());
    }

    #[DataProvider('provideInvalidDocuments')]
    public function testParseInvalidDocument(string $document, string $expectedMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->parser->parse($document);
    }

    public static function provideInvalidDocuments(): iterable
    {
        yield 'not json' => ['{', 'The link set is not a valid JSON document.'];
        yield 'not an object' => ['[]', 'The link set must be a JSON object holding a "linkset" member.'];
        yield 'no linkset member' => ['{"links": []}', 'The link set must be a JSON object holding a "linkset" member.'];
        yield 'context is not an object' => ['{"linkset": ["nope"]}', 'Each member of the "linkset" array must be a link context object.'];
        yield 'anchor is not a string' => ['{"linkset": [{"anchor": 42}]}', 'The "anchor" member of a link context object must be a string.'];
        yield 'targets are not an array' => ['{"linkset": [{"next": "/foo"}]}', 'The "next" member of a link context object must be an array of link target objects.'];
        yield 'target without href' => ['{"linkset": [{"next": [{"type": "text/html"}]}]}', 'Each link target object of the "next" relation type must hold an "href" member.'];
        yield 'href is not a string' => ['{"linkset": [{"next": [{"href": 42}]}]}', 'Each link target object of the "next" relation type must hold an "href" member.'];
        yield 'internationalized attribute without value' => ['{"linkset": [{"next": [{"href": "/foo", "title*": [{"language": "de"}]}]}]}', 'The "title*" target attribute must hold objects with a "value" member.'];
        yield 'internationalized attribute with an invalid language' => ['{"linkset": [{"next": [{"href": "/foo", "title*": [{"value": "a", "language": 42}]}]}]}', 'The "language" member of the "title*" target attribute must be a string.'];
        yield 'attribute holding an object' => ['{"linkset": [{"next": [{"href": "/foo", "foo": [{"bar": "baz"}]}]}]}', 'The "foo" target attribute must hold string values.'];
    }
}
