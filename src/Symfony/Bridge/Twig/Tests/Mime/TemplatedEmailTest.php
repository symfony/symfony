<?php

namespace Symfony\Bridge\Twig\Tests\Mime;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\MimeMessageNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

class TemplatedEmailTest extends TestCase
{
    public function test()
    {
        $email = new TemplatedEmail();
        $email->context($context = ['product' => 'Symfony']);
        $this->assertEquals($context, $email->getContext());

        $email->textTemplate($template = 'text');
        $this->assertEquals($template, $email->getTextTemplate());

        $email->htmlTemplate($template = 'html');
        $this->assertEquals($template, $email->getHtmlTemplate());
    }

    public function testSerialize()
    {
        $email = (new TemplatedEmail())
            ->textTemplate('text.txt.twig')
            ->htmlTemplate('text.html.twig')
            ->context($context = ['a' => 'b'])
        ;

        $email = unserialize(serialize($email));
        $this->assertEquals('text.txt.twig', $email->getTextTemplate());
        $this->assertEquals('text.html.twig', $email->getHtmlTemplate());
        $this->assertEquals($context, $email->getContext());
    }

    public function testSymfonySerialize()
    {
        // we don't add from/sender to check that validation is not triggered to serialize an email
        $e = new TemplatedEmail();
        $e->to('you@example.com');
        $e->textTemplate('email.txt.twig');
        $e->htmlTemplate('email.html.twig');
        $e->context(['foo' => 'bar']);
        $e->attach('Some Text file', 'test.txt');
        $expected = clone $e;

        $expectedJson = <<<EOF
{
    "htmlTemplate": "email.html.twig",
    "textTemplate": "email.txt.twig",
    "context": {
        "foo": "bar"
    },
    "text": null,
    "textCharset": null,
    "html": null,
    "htmlCharset": null,
    "attachments": [
        {
            "body": "Some Text file",
            "name": "test.txt",
            "content-type": null,
            "inline": false
        }
    ],
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
    "body": null,
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
        $this->assertSame($expectedJson, json_encode(json_decode($serialized), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $n = $serializer->deserialize($serialized, TemplatedEmail::class, 'json');
        $serialized = $serializer->serialize($e, 'json');
        $this->assertSame($expectedJson, json_encode(json_decode($serialized), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $n->from('fabien@symfony.com');
        $expected->from('fabien@symfony.com');
        $this->assertEquals($expected->getHeaders(), $n->getHeaders());
        $this->assertEquals($expected->getBody(), $n->getBody());
    }
}
