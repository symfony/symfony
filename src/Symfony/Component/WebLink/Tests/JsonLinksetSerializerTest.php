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
use Symfony\Component\WebLink\JsonLinksetSerializer;
use Symfony\Component\WebLink\Link;

class JsonLinksetSerializerTest extends TestCase
{
    private JsonLinksetSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonLinksetSerializer();
    }

    public function testSerializeEmpty()
    {
        self::assertSame('{"linkset":[]}', $this->serializer->serialize([]));
    }

    public function testSerializeSingleLink()
    {
        $links = [
            (new Link('next', 'https://example.com/foo'))->withAttribute('anchor', 'https://example.net/bar'),
        ];

        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {"linkset": [
                {"anchor": "https://example.net/bar", "next": [{"href": "https://example.com/foo"}]}
            ]}
            JSON, $this->serializer->serialize($links));
    }

    public function testSerializeGroupsLinksSharingTheSameContextAndRelation()
    {
        $links = [
            (new Link('item', 'https://example.com/foo1'))->withAttribute('anchor', 'https://example.net/bar'),
            (new Link('item', 'https://example.com/foo2'))->withAttribute('anchor', 'https://example.net/bar'),
        ];

        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {"linkset": [
                {"anchor": "https://example.net/bar", "item": [
                    {"href": "https://example.com/foo1"},
                    {"href": "https://example.com/foo2"}
                ]}
            ]}
            JSON, $this->serializer->serialize($links));
    }

    public function testSerializeSplitsDistinctContexts()
    {
        $links = [
            (new Link('next', 'https://example.com/foo1'))->withAttribute('anchor', 'https://example.net/bar'),
            (new Link('https://example.com/relations/baz', 'https://example.com/foo2'))->withAttribute('anchor', 'https://example.net/boo'),
        ];

        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {"linkset": [
                {"anchor": "https://example.net/bar", "next": [{"href": "https://example.com/foo1"}]},
                {"anchor": "https://example.net/boo", "https://example.com/relations/baz": [{"href": "https://example.com/foo2"}]}
            ]}
            JSON, $this->serializer->serialize($links));
    }

    public function testSerializeWithoutAnchorOmitsTheContext()
    {
        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {"linkset": [
                {"next": [{"href": "https://example.com/foo"}]}
            ]}
            JSON, $this->serializer->serialize([new Link('next', 'https://example.com/foo')]));
    }

    public function testSerializeDistinguishesAnEmptyAnchorFromNoAnchor()
    {
        $links = [
            (new Link('next', 'https://example.com/foo'))->withAttribute('anchor', ''),
            new Link('next', 'https://example.com/bar'),
        ];

        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {"linkset": [
                {"anchor": "", "next": [{"href": "https://example.com/foo"}]},
                {"next": [{"href": "https://example.com/bar"}]}
            ]}
            JSON, $this->serializer->serialize($links));
    }

    public function testSerializeNumericAnchor()
    {
        $links = [(new Link('next', 'https://example.com/foo'))->withAttribute('anchor', '123')];

        self::assertJsonStringEqualsJsonString('{"linkset":[{"anchor":"123","next":[{"href":"https://example.com/foo"}]}]}', $this->serializer->serialize($links));
    }

    public function testSerializeRepeatsMultipleRelations()
    {
        $links = [(new Link('alternate', 'https://example.com/foo'))->withRel('next')];

        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {"linkset": [
                {
                    "alternate": [{"href": "https://example.com/foo"}],
                    "next": [{"href": "https://example.com/foo"}]
                }
            ]}
            JSON, $this->serializer->serialize($links));
    }

    public function testSerializeTargetAttributesDefinedByWebLinking()
    {
        $links = [
            (new Link('next', 'https://example.com/foo'))
                ->withAttribute('anchor', 'https://example.net/bar')
                ->withAttribute('type', 'text/html')
                ->withAttribute('hreflang', ['en', 'de'])
                ->withAttribute('media', 'screen'),
        ];

        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {"linkset": [
                {"anchor": "https://example.net/bar", "next": [{
                    "href": "https://example.com/foo",
                    "type": "text/html",
                    "hreflang": ["en", "de"],
                    "media": "screen"
                }]}
            ]}
            JSON, $this->serializer->serialize($links));
    }

    public function testSerializeWrapsSingleValuedHreflangInAnArray()
    {
        $links = [(new Link('next', 'https://example.com/foo'))->withAttribute('hreflang', 'en')];

        self::assertJsonStringEqualsJsonString('{"linkset":[{"next":[{"href":"https://example.com/foo","hreflang":["en"]}]}]}', $this->serializer->serialize($links));
    }

    public function testSerializeKeepsOnlyTheFirstValueOfNonRepeatableAttributes()
    {
        $links = [(new Link('next', 'https://example.com/foo'))->withAttribute('type', ['text/html', 'text/plain'])];

        self::assertJsonStringEqualsJsonString('{"linkset":[{"next":[{"href":"https://example.com/foo","type":"text/html"}]}]}', $this->serializer->serialize($links));
    }

    public function testSerializeInternationalizedTargetAttributes()
    {
        $links = [
            (new Link('next', 'https://example.com/foo'))
                ->withAttribute('anchor', 'https://example.net/bar')
                ->withAttribute('title', 'Next chapter')
                ->withAttribute('title*', "UTF-8'de'n%c3%a4chstes%20Kapitel"),
        ];

        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {"linkset": [
                {"anchor": "https://example.net/bar", "next": [{
                    "href": "https://example.com/foo",
                    "title": "Next chapter",
                    "title*": [{"value": "nächstes Kapitel", "language": "de"}]
                }]}
            ]}
            JSON, $this->serializer->serialize($links));
    }

    public function testSerializeInternationalizedTargetAttributeWithoutLanguage()
    {
        $links = [(new Link('next', 'https://example.com/foo'))->withAttribute('title*', "UTF-8''Next%20chapter")];

        self::assertJsonStringEqualsJsonString('{"linkset":[{"next":[{"href":"https://example.com/foo","title*":[{"value":"Next chapter"}]}]}]}', $this->serializer->serialize($links));
    }

    public function testSerializeExtensionTargetAttributesAsArrays()
    {
        $links = [
            (new Link('next', 'https://example.com/foo'))
                ->withAttribute('anchor', 'https://example.net/bar')
                ->withAttribute('type', 'text/html')
                ->withAttribute('foo', 'foovalue')
                ->withAttribute('bar', ['barone', 'bartwo'])
                ->withAttribute('baz*', "UTF-8'en'bazvalue"),
        ];

        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {"linkset": [
                {"anchor": "https://example.net/bar", "next": [{
                    "href": "https://example.com/foo",
                    "type": "text/html",
                    "foo": ["foovalue"],
                    "bar": ["barone", "bartwo"],
                    "baz*": [{"value": "bazvalue", "language": "en"}]
                }]}
            ]}
            JSON, $this->serializer->serialize($links));
    }

    public function testSerializeBooleanAttributes()
    {
        $links = [
            (new Link('preload', 'https://example.com/foo'))
                ->withAttribute('nopush', true)
                ->withAttribute('nofollow', false),
        ];

        self::assertJsonStringEqualsJsonString('{"linkset":[{"preload":[{"href":"https://example.com/foo","nopush":[""]}]}]}', $this->serializer->serialize($links));
    }

    public function testSerializeSkipsTemplatedLinks()
    {
        $links = [
            new Link('item', 'https://example.com/users/{id}'),
            new Link('next', 'https://example.com/foo'),
        ];

        self::assertJsonStringEqualsJsonString('{"linkset":[{"next":[{"href":"https://example.com/foo"}]}]}', $this->serializer->serialize($links));
    }

    public function testSerializeSkipsLinksWithoutARelationType()
    {
        $links = [
            new Link(null, 'https://example.com/foo'),
            new Link('next', 'https://example.com/bar'),
        ];

        self::assertJsonStringEqualsJsonString('{"linkset":[{"next":[{"href":"https://example.com/bar"}]}]}', $this->serializer->serialize($links));
    }

    public function testSerializeTreatsAFalseAnchorAsAbsent()
    {
        $links = [(new Link('next', 'https://example.com/foo'))->withAttribute('anchor', false)];

        self::assertJsonStringEqualsJsonString('{"linkset":[{"next":[{"href":"https://example.com/foo"}]}]}', $this->serializer->serialize($links));
    }

    public function testSerializeThrowsOnTheAnchorRelationType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A link with the "anchor" relation type cannot be represented in an "application/linkset+json" document.');

        $this->serializer->serialize([(new Link('anchor', 'https://example.com/foo'))->withAttribute('anchor', 'https://example.net/bar')]);
    }

    public function testSerializeThrowsWhenTheLinksCannotBeEncoded()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The link set cannot be serialized to JSON: "Malformed UTF-8 characters, possibly incorrectly encoded".');

        $this->serializer->serialize([(new Link('next', '/foo'))->withAttribute('title', "\xB1\x31")]);
    }

    public function testSerializeThrowsWhenTheLinksCannotBeEncodedWhateverTheFlags()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->serializer->serialize([(new Link('next', '/foo'))->withAttribute('title', "\xB1\x31")], \JSON_THROW_ON_ERROR);
    }

    public function testSerializeForwardsJsonEncodeFlags()
    {
        $links = [new Link('next', 'https://example.com/foo')];

        self::assertStringContainsString('https://example.com/foo', $this->serializer->serialize($links, \JSON_UNESCAPED_SLASHES));
        self::assertStringContainsString('https:\/\/example.com\/foo', $this->serializer->serialize($links));
    }
}
