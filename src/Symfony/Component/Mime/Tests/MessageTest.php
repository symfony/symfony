<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\MimeMessageNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

class MessageTest extends TestCase
{
    public function testConstruct()
    {
        $m = new Message();
        self::assertNull($m->getBody());
        self::assertEquals(new Headers(), $m->getHeaders());

        $m = new Message($h = (new Headers())->addDateHeader('Date', new \DateTime()), $b = new TextPart('content'));
        self::assertSame($b, $m->getBody());
        self::assertEquals($h, $m->getHeaders());

        $m = new Message();
        $m->setBody($b);
        $m->setHeaders($h);
        self::assertSame($b, $m->getBody());
        self::assertSame($h, $m->getHeaders());
    }

    public function testGetPreparedHeadersThrowsWhenNoFrom()
    {
        self::expectException(\LogicException::class);
        (new Message())->getPreparedHeaders();
    }

    public function testGetPreparedHeadersCloneHeaders()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        self::assertNotSame($message->getPreparedHeaders(), $message->getHeaders());
    }

    public function testGetPreparedHeadersSetRequiredHeaders()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $headers = $message->getPreparedHeaders();
        self::assertTrue($headers->has('MIME-Version'));
        self::assertTrue($headers->has('Message-ID'));
        self::assertTrue($headers->has('Date'));
        self::assertFalse($headers->has('Bcc'));
    }

    public function testGetPreparedHeaders()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $h = $message->getPreparedHeaders();
        self::assertCount(4, iterator_to_array($h->all()));
        self::assertEquals(new MailboxListHeader('From', [new Address('fabien@symfony.com')]), $h->get('From'));
        self::assertEquals(new UnstructuredHeader('MIME-Version', '1.0'), $h->get('mime-version'));
        self::assertTrue($h->has('Message-Id'));
        self::assertTrue($h->has('Date'));

        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $message->getHeaders()->addDateHeader('Date', $n = new \DateTimeImmutable());
        self::assertEquals($n, $message->getPreparedHeaders()->get('Date')->getDateTime());

        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $message->getHeaders()->addMailboxListHeader('Bcc', ['fabien@symfony.com']);
        self::assertNull($message->getPreparedHeaders()->get('Bcc'));
    }

    public function testGetPreparedHeadersWithNoFrom()
    {
        self::expectException(\LogicException::class);
        (new Message())->getPreparedHeaders();
    }

    public function testGetPreparedHeadersWithNamedFrom()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', [new Address('fabien@symfony.com', 'Fabien')]);
        $h = $message->getPreparedHeaders();
        self::assertEquals(new MailboxListHeader('From', [new Address('fabien@symfony.com', 'Fabien')]), $h->get('From'));
        self::assertTrue($h->has('Message-Id'));
    }

    public function testGetPreparedHeadersHasSenderWhenNeeded()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        self::assertNull($message->getPreparedHeaders()->get('Sender'));

        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com', 'lucas@symfony.com']);
        self::assertEquals('fabien@symfony.com', $message->getPreparedHeaders()->get('Sender')->getAddress()->getAddress());

        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com', 'lucas@symfony.com']);
        $message->getHeaders()->addMailboxHeader('Sender', 'thomas@symfony.com');
        self::assertEquals('thomas@symfony.com', $message->getPreparedHeaders()->get('Sender')->getAddress()->getAddress());
    }

    public function testToString()
    {
        $message = new Message();
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $expected = <<<EOF
From: fabien@symfony.com
MIME-Version: 1.0
Date: %s
Message-ID: <%s@symfony.com>
Content-Type: text/plain; charset=utf-8
Content-Transfer-Encoding: quoted-printable


EOF;
        self::assertStringMatchesFormat($expected, str_replace("\r\n", "\n", $message->toString()));
        self::assertStringMatchesFormat($expected, str_replace("\r\n", "\n", implode('', iterator_to_array($message->toIterable(), false))));

        $message = new Message(null, new TextPart('content'));
        $message->getHeaders()->addMailboxListHeader('From', ['fabien@symfony.com']);
        $expected = <<<EOF
From: fabien@symfony.com
MIME-Version: 1.0
Date: %s
Message-ID: <%s@symfony.com>
Content-Type: text/plain; charset=utf-8
Content-Transfer-Encoding: quoted-printable

content
EOF;
        self::assertStringMatchesFormat($expected, str_replace("\r\n", "\n", $message->toString()));
        self::assertStringMatchesFormat($expected, str_replace("\r\n", "\n", implode('', iterator_to_array($message->toIterable(), false))));
    }

    public function testSymfonySerialize()
    {
        // we don't add from/sender to check that it's not needed to serialize an email
        $body = new MixedPart(
            new AlternativePart(
                new TextPart('Text content'),
                new TextPart('HTML content', 'utf-8', 'html')
            ),
            new DataPart('text data', 'text.txt')
        );
        $e = new Message((new Headers())->addMailboxListHeader('To', ['you@example.com']), $body);
        $expected = clone $e;

        $expectedJson = <<<EOF
{
    "headers": {
        "to": [
            {
                "addresses": [
                    {
                        "address": "you@example.com",
                        "name": ""
                    }
                ],
                "name": "To",
                "lineLength": 76,
                "lang": null,
                "charset": "utf-8"
            }
        ]
    },
    "body": {
        "boundary": null,
        "parts": [
            {
                "boundary": null,
                "parts": [
                    {
                        "body": "Text content",
                        "charset": "utf-8",
                        "subtype": "plain",
                        "disposition": null,
                        "name": null,
                        "encoding": "quoted-printable",
                        "headers": [],
                        "class": "Symfony\\\\Component\\\\Mime\\\\Part\\\TextPart"
                    },
                    {
                        "body": "HTML content",
                        "charset": "utf-8",
                        "subtype": "html",
                        "disposition": null,
                        "name": null,
                        "encoding": "quoted-printable",
                        "headers": [],
                        "class": "Symfony\\\\Component\\\\Mime\\\\Part\\\\TextPart"
                    }
                ],
                "headers": [],
                "class": "Symfony\\\\Component\\\\Mime\\\\Part\\\\Multipart\\\\AlternativePart"
            },
            {
                "filename": "text.txt",
                "mediaType": "application",
                "body": "text data",
                "charset": null,
                "subtype": "octet-stream",
                "disposition": "attachment",
                "name": "text.txt",
                "encoding": "base64",
                "headers": [],
                "class": "Symfony\\\\Component\\\\Mime\\\\Part\\\\DataPart"
            }
        ],
        "headers": [],
        "class": "Symfony\\\\Component\\\\Mime\\\\Part\\\\Multipart\\\\MixedPart"
    },
    "message": null
}
EOF;

        $extractor = new PhpDocExtractor();
        $propertyNormalizer = new PropertyNormalizer(null, null, $extractor);
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MimeMessageNormalizer($propertyNormalizer),
            new ObjectNormalizer(null, null, null, $extractor),
            $propertyNormalizer,
        ], [new JsonEncoder()]);

        $serialized = $serializer->serialize($e, 'json');
        self::assertSame($expectedJson, json_encode(json_decode($serialized), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $n = $serializer->deserialize($serialized, Message::class, 'json');
        self::assertEquals($expected->getHeaders(), $n->getHeaders());

        $serialized = $serializer->serialize($e, 'json');
        self::assertSame($expectedJson, json_encode(json_decode($serialized), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }
}
