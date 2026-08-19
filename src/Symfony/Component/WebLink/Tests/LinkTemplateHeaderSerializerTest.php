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

use PHPUnit\Framework\TestCase;
use Symfony\Component\WebLink\Exception\InvalidArgumentException;
use Symfony\Component\WebLink\Link;
use Symfony\Component\WebLink\LinkTemplateHeaderSerializer;

class LinkTemplateHeaderSerializerTest extends TestCase
{
    private LinkTemplateHeaderSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new LinkTemplateHeaderSerializer();
    }

    public function testSerializeEmpty()
    {
        self::assertNull($this->serializer->serialize([]));
    }

    public function testSerialize()
    {
        self::assertSame('"/{username}"; rel="item"', $this->serializer->serialize([new Link('item', '/{username}')]));
    }

    public function testSerializeSeveralTemplates()
    {
        $links = [
            new Link('item', '/{username}'),
            (new Link('alternate', '/{username}{?format}'))->withRel('next'),
        ];

        self::assertSame('"/{username}"; rel="item", "/{username}{?format}"; rel="alternate next"', $this->serializer->serialize($links));
    }

    public function testSerializeTemplatedAnchor()
    {
        $links = [(new Link('author', '/books/{book_id}/author'))->withAttribute('anchor', '#{book_id}')];

        self::assertSame('"/books/{book_id}/author"; rel="author"; anchor="#{book_id}"', $this->serializer->serialize($links));
    }

    public function testSerializeVarBase()
    {
        $links = [
            (new Link('https://example.org/rel/widget', '/widgets/{widget_id}'))
                ->withAttribute('var-base', 'https://example.org/vars/'),
        ];

        self::assertSame('"/widgets/{widget_id}"; rel="https://example.org/rel/widget"; var-base="https://example.org/vars/"', $this->serializer->serialize($links));
    }

    public function testSerializeNonAsciiAttributeAsADisplayString()
    {
        $links = [(new Link('author', '/authors/{id}'))->withAttribute('title', 'Björn Järnsida')];

        self::assertSame('"/authors/{id}"; rel="author"; title=%"Bj%c3%b6rn J%c3%a4rnsida"', $this->serializer->serialize($links));
    }

    public function testSerializeEscapesStrings()
    {
        $links = [(new Link('item', '/{id}'))->withAttribute('title', 'a "quoted" \\ value')];

        self::assertSame('"/{id}"; rel="item"; title="a \"quoted\" \\\\ value"', $this->serializer->serialize($links));
    }

    public function testSerializeNonPrintableAttributeAsADisplayString()
    {
        $links = [(new Link('item', '/{id}'))->withAttribute('title', "Hello\n")];

        self::assertSame('"/{id}"; rel="item"; title=%"Hello%0a"', $this->serializer->serialize($links));
    }

    public function testSerializeBooleanAttributes()
    {
        $links = [
            (new Link('item', '/{id}'))
                ->withAttribute('nopush', true)
                ->withAttribute('nofollow', false),
        ];

        self::assertSame('"/{id}"; rel="item"; nopush', $this->serializer->serialize($links));
    }

    public function testSerializeRepeatedAttributes()
    {
        $links = [(new Link('item', '/{id}'))->withAttribute('hreflang', ['fr', 'de'])];

        self::assertSame('"/{id}"; rel="item"; hreflang="fr"; hreflang="de"', $this->serializer->serialize($links));
    }

    public function testSerializeLowercasesAttributeNames()
    {
        $links = [(new Link('item', '/{id}'))->withAttribute('Title', 'Hello')];

        self::assertSame('"/{id}"; rel="item"; title="Hello"', $this->serializer->serialize($links));
    }

    public function testSerializeWithoutRel()
    {
        self::assertSame('"/{id}"', $this->serializer->serialize([new Link(null, '/{id}')]));
    }

    public function testSerializeSkipsLinksThatAreNotTemplated()
    {
        $links = [
            new Link('preload', '/style.css'),
            new Link('item', '/{id}'),
        ];

        self::assertSame('"/{id}"; rel="item"', $this->serializer->serialize($links));
    }

    public function testSerializeThrowsOnAnInvalidParameterKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "0invalid" target attribute cannot be serialized as a structured field parameter key.');

        $this->serializer->serialize([(new Link('item', '/{id}'))->withAttribute('0invalid', 'value')]);
    }

    public function testSerializeThrowsOnANonUtf8Value()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-ASCII strings must be encoded in UTF-8 to be serialized as structured field display strings.');

        $this->serializer->serialize([(new Link('item', '/{id}'))->withAttribute('title', "Bj\xF6rn")]);
    }
}
