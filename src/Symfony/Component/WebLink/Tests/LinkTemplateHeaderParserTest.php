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
use Symfony\Component\WebLink\Link;
use Symfony\Component\WebLink\LinkTemplateHeaderParser;
use Symfony\Component\WebLink\LinkTemplateHeaderSerializer;

class LinkTemplateHeaderParserTest extends TestCase
{
    private LinkTemplateHeaderParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LinkTemplateHeaderParser();
    }

    public function testParseEmpty()
    {
        self::assertSame([], $this->parser->parse('')->getLinks());
        self::assertSame([], $this->parser->parse('   ')->getLinks());
    }

    public function testParse()
    {
        $links = $this->parser->parse('"/{username}"; rel="item"')->getLinks();

        self::assertCount(1, $links);
        self::assertSame('/{username}', $links[0]->getHref());
        self::assertTrue($links[0]->isTemplated());
        self::assertSame(['item'], $links[0]->getRels());
        self::assertSame([], $links[0]->getAttributes());
    }

    public function testParseSeveralHeaders()
    {
        $links = $this->parser->parse([
            '"/{username}"; rel="item"',
            '"/books/{book_id}/author"; rel="author"; anchor="#{book_id}"',
        ])->getLinks();

        self::assertCount(2, $links);
        self::assertSame(['item'], $links[0]->getRels());
        self::assertSame('/books/{book_id}/author', $links[1]->getHref());
        self::assertSame(['author'], $links[1]->getRels());
        self::assertSame(['anchor' => '#{book_id}'], $links[1]->getAttributes());
    }

    public function testParseSeveralRelations()
    {
        $links = $this->parser->parse('"/{username}"; rel="alternate  next"')->getLinks();

        self::assertSame(['alternate', 'next'], $links[0]->getRels());
    }

    public function testParseWithoutRelation()
    {
        $links = $this->parser->parse('"/{username}"')->getLinks();

        self::assertCount(1, $links);
        self::assertSame([], $links[0]->getRels());
    }

    public function testParseVarBase()
    {
        $links = $this->parser->parse('"/widgets/{widget_id}"; rel="https://example.org/rel/widget"; var-base="https://example.org/vars/"')->getLinks();

        self::assertSame(['https://example.org/rel/widget'], $links[0]->getRels());
        self::assertSame(['var-base' => 'https://example.org/vars/'], $links[0]->getAttributes());
    }

    #[DataProvider('provideParameterCases')]
    public function testParseParameterTypes(string $header, array $expectedAttributes)
    {
        $links = $this->parser->parse($header)->getLinks();

        self::assertCount(1, $links);
        self::assertSame($expectedAttributes, $links[0]->getAttributes());
    }

    public static function provideParameterCases(): iterable
    {
        yield 'string' => ['"/{id}"; title="Hello"', ['title' => 'Hello']];
        yield 'escaped string' => ['"/{id}"; title="a \"quoted\" \\\\ value"', ['title' => 'a "quoted" \\ value']];
        yield 'display string' => ['"/{id}"; title=%"Bj%c3%b6rn J%c3%a4rnsida"', ['title' => 'Björn Järnsida']];
        yield 'token' => ['"/{id}"; type=text/html', ['type' => 'text/html']];
        yield 'true boolean' => ['"/{id}"; nopush', ['nopush' => true]];
        yield 'explicit boolean' => ['"/{id}"; nopush=?1; nofollow=?0', ['nopush' => true, 'nofollow' => false]];
        yield 'integer' => ['"/{id}"; weight=42', ['weight' => 42]];
        yield 'decimal' => ['"/{id}"; pr=0.7', ['pr' => 0.7]];
        yield 'negative integer' => ['"/{id}"; weight=-42', ['weight' => -42]];
        yield 'spaces after the semicolon' => ['"/{id}";   title="Hello"', ['title' => 'Hello']];
        yield 'last occurrence wins' => ['"/{id}"; title="a"; title="b"', ['title' => 'b']];
    }

    #[DataProvider('provideInvalidHeaders')]
    public function testParseInvalidHeaderIsIgnored(string $header)
    {
        self::assertSame([], $this->parser->parse($header)->getLinks());
    }

    public static function provideInvalidHeaders(): iterable
    {
        yield 'unterminated string' => ['"/{id}'];
        yield 'unterminated display string' => ['%"/{id}'];
        yield 'invalid percent-encoded byte' => ['%"%zz"'];
        yield 'invalid utf-8 display string' => ['%"%ff"'];
        yield 'member is not a string' => ['/{id}; rel="item"'];
        yield 'trailing comma' => ['"/{id}"; rel="item",'];
        yield 'missing comma' => ['"/{id}" "/{name}"'];
        yield 'uppercase parameter key' => ['"/{id}"; REL="item"'];
        yield 'missing parameter key' => ['"/{id}"; ="item"'];
        yield 'invalid escape sequence' => ['"/{id}"; title="a\\nb"'];
        yield 'byte sequence parameter' => ['"/{id}"; sig=:cHJldGVuZCB0aGlzIGlzIGJpbmFyeQ==:'];
        yield 'decimal with a 13-digit integer part' => ['"/{id}"; pr=1234567890123.5'];
        yield 'decimal with a 4-digit fraction' => ['"/{id}"; pr=2.5000'];
        yield '16-digit integer' => ['"/{id}"; weight=1234567890123456'];
    }

    public function testParseIgnoresTheWholeHeaderWhenOneMemberIsInvalid()
    {
        self::assertSame([], $this->parser->parse('"/{id}"; rel="item", nope')->getLinks());
    }

    public function testParseIgnoresARelParameterThatIsNotAString()
    {
        $links = $this->parser->parse('"/{id}"; rel=42; title="Hello"')->getLinks();

        self::assertCount(1, $links);
        self::assertSame([], $links[0]->getRels());
        self::assertSame(['title' => 'Hello'], $links[0]->getAttributes());
    }

    public function testRoundTrip()
    {
        $links = [
            (new Link('item', '/{username}'))->withAttribute('title', 'Björn Järnsida'),
            (new Link('author', '/books/{book_id}/author'))->withAttribute('anchor', '#{book_id}'),
        ];

        $header = (new LinkTemplateHeaderSerializer())->serialize($links);

        self::assertEquals($links, $this->parser->parse($header)->getLinks());
    }
}
