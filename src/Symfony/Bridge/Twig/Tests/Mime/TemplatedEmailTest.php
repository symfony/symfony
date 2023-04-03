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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\MimeMessageNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

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

        $twig = new Environment(new ArrayLoader(['testTemplate' => 'Twig content']));
        $twigTemplate = $twig->load('testTemplate');
        $email->textTemplate($twigTemplate);
        $this->assertEquals($twigTemplate, $email->getTextTemplate());

        $email->htmlTemplate($twigTemplate);
        $this->assertEquals($twigTemplate, $email->getHtmlTemplate());

        $this->expectException(InvalidArgumentException::class);
        $email->textTemplate(['some array']);

        $this->expectException(InvalidArgumentException::class);
        $email->htmlTemplate(['some array']);
    }

    public function testSerialize()
    {
        $email = (new TemplatedEmail())
            ->textTemplate('text.txt.twig')
            ->htmlTemplate('text.html.twig')
            ->context($context = ['a' => 'b'])
        ;

        $email = \unserialize(\serialize($email));
        $this->assertEquals('text.txt.twig', $email->getTextTemplate());
        $this->assertEquals('text.html.twig', $email->getHtmlTemplate());
        $this->assertEquals($context, $email->getContext());
    }

    public function testSerializeTemplateWrapper()
    {
        $twig = new Environment(new ArrayLoader(['testTemplate' => 'Twig content']));
        $twigTemplate = $twig->load('testTemplate');

        $email = (new TemplatedEmail())
            ->textTemplate($twigTemplate)
            ->htmlTemplate($twigTemplate)
            ->context($context = ['a' => 'b'])
        ;

        $email = \unserialize(\serialize($email));
        $this->assertEquals($twigTemplate, $email->getTextTemplate());
        $this->assertEquals($twigTemplate, $email->getHtmlTemplate());
        $this->assertEquals($context, $email->getContext());
        $this->assertEquals('Twig content', $twig->render($email->getTextTemplate()));
        $this->assertEquals('Twig content', $twig->render($email->getHtmlTemplate()));
    }

    public function testSymfonySerialize()
    {
        // we don't add from/sender to check that validation is not triggered to serialize an email
        $e = new TemplatedEmail();
        $e->to('you@example.com');
        $e->textTemplate('email.txt.twig');
        $e->htmlTemplate('email.html.twig');
        $e->context(['foo' => 'bar']);
        $e->addPart(new DataPart('Some Text file', 'test.txt'));
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

        $serialized = $serializer->serialize($e, 'json', [ObjectNormalizer::IGNORED_ATTRIBUTES => ['cachedBody']]);
        $this->assertStringMatchesFormat($expectedJson, \json_encode(\json_decode($serialized), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $n = $serializer->deserialize($serialized, TemplatedEmail::class, 'json');
        $serialized = $serializer->serialize($e, 'json', [ObjectNormalizer::IGNORED_ATTRIBUTES => ['cachedBody']]);
        $this->assertStringMatchesFormat($expectedJson, \json_encode(\json_decode($serialized), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $n->from('fabien@symfony.com');
        $expected->from('fabien@symfony.com');
        $this->assertEquals($expected->getHeaders(), $n->getHeaders());
        $this->assertEquals($expected->getBody(), $n->getBody());
    }

    public function testSymfonySerializeTemplateWrapper()
    {
        $twig = new Environment(new ArrayLoader(['testTemplate' => 'Twig content']));
        $twigTemplate = $twig->load('testTemplate');

        // we don't add from/sender to check that validation is not triggered to serialize an email
        $e = new TemplatedEmail();
        $e->to('you@example.com');
        $e->textTemplate($twigTemplate);
        $e->htmlTemplate($twigTemplate);
        $e->context(['foo' => 'bar']);
        $e->addPart(new DataPart('Some Text file', 'test.txt'));
        $expected = clone $e;

        $expectedJson = <<<EOF
{
    "htmlTemplate": {
        "blockNames": [],
        "sourceContext": {
            "code": "",
            "name": "testTemplate",
            "path": ""
        },
        "templateName": "testTemplate"
    },
    "textTemplate": {
        "blockNames": [],
        "sourceContext": {
            "code": "",
            "name": "testTemplate",
            "path": ""
        },
        "templateName": "testTemplate"
    },
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

        $serialized = $serializer->serialize($e, 'json', [ObjectNormalizer::IGNORED_ATTRIBUTES => ['cachedBody']]);
        $this->assertStringMatchesFormat($expectedJson, \json_encode(\json_decode($serialized), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $n = $serializer->deserialize($serialized, TemplatedEmail::class, 'json');
        $serialized = $serializer->serialize($e, 'json', [ObjectNormalizer::IGNORED_ATTRIBUTES => ['cachedBody']]);
        $this->assertStringMatchesFormat($expectedJson, \json_encode(\json_decode($serialized), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $n->from('fabien@symfony.com');
        $expected->from('fabien@symfony.com');
        $this->assertEquals($expected->getHeaders(), $n->getHeaders());
        $this->assertEquals($expected->getBody(), $n->getBody());
        $this->assertEquals($twig->render($expected->getTextTemplate()), $twig->render($n->getTextTemplate()));
        $this->assertEquals($twig->render($expected->getTextTemplate()), $twig->render($n->getHtmlTemplate()));
    }
}
