<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Mime;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\MimeMessageNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

class TemplatedEmailToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

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
            ->locale($locale = 'fr_FR')
        ;

        $email = unserialize(serialize($email));
        $this->assertEquals('text.txt.twig', $email->getTextTemplate());
        $this->assertEquals('text.html.twig', $email->getHtmlTemplate());
        $this->assertEquals($context, $email->getContext());
        $this->assertEquals($locale, $email->getLocale());
    }

    public function testSymfonySerialize()
    {
        // we don't add from/sender to check that validation is not triggered to serialize an email
        $e = new TemplatedEmail();
        $e->to('you@example.com');
        $e->textTemplate('email.txt.twig');
        $e->htmlTemplate('email.html.twig');
        $e->locale('en');
        $e->context(['foo' => 'bar']);
        $e->addPart(new DataPart('Some Text file', 'test.txt'));
        $expected = clone $e;

        $expectedJson = <<<EOF
            {
                "htmlTemplate": "email.html.twig",
                "textTemplate": "email.txt.twig",
                "locale": "en",
                "context": {
                    "foo": "bar"
                },
                "text": null,
                "textCharset": null,
                "html": null,
                "htmlCharset": null,
                "attachments": [
                    {%A
                        "body": "Some Text file",%A
                        "name": "test.txt",%A
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
                "class": "Symfony\\\\Bridge\\\\Twig\\\\Mime\\\\TemplatedEmail"
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

        $serialized = $serializer->serialize($e, 'json', [ObjectNormalizer::IGNORED_ATTRIBUTES => ['cachedBody']]);
        $this->assertStringMatchesFormat($expectedJson, json_encode(json_decode($serialized), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $n = $serializer->deserialize($serialized, TemplatedEmail::class, 'json');
        $serialized = $serializer->serialize($e, 'json', [ObjectNormalizer::IGNORED_ATTRIBUTES => ['cachedBody']]);
        $this->assertStringMatchesFormat($expectedJson, json_encode(json_decode($serialized), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $n->from('fabien@symfony.com');
        $expected->from('fabien@symfony.com');
        $this->assertEquals($expected->getHeaders(), $n->getHeaders());
        $this->assertEquals($expected->getBody(), $n->getBody());
    }

    #[DataProvider('provideTrampolineSlots')]
    public function testUnserializeRejectsObjectInTypedStringProperty(int $slot)
    {
        $email = (new TemplatedEmail())
            ->htmlTemplate('html.twig')
            ->textTemplate('text.twig')
            ->locale('en')
        ;
        $data = $email->__serialize();
        $data[$slot] = new TemplatedEmailToStringGadget();
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(TemplatedEmail::class), TemplatedEmail::class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }
        $payload .= '}';
        TemplatedEmailToStringGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(TemplatedEmailToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }

    public static function provideTrampolineSlots(): iterable
    {
        // [htmlTemplate, textTemplate, context, parentData, locale]
        yield 'htmlTemplate' => [0];
        yield 'textTemplate' => [1];
        yield 'locale' => [4];
    }
}
