<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Translation\TwigExtractor;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\LoaderInterface;

class TwigExtractorTest extends TestCase
{
    /**
     * @dataProvider getExtractData
     */
    public function testExtract($template, $messages)
    {
        $loader = $this->createMock(LoaderInterface::class);
        $twig = new Environment($loader, [
            'strict_variables' => true,
            'debug' => true,
            'cache' => false,
            'autoescape' => false,
        ]);
        $twig->addExtension(new TranslationExtension($this->createMock(TranslatorInterface::class)));

        $extractor = new TwigExtractor($twig);
        $extractor->setPrefix('prefix');
        $catalogue = new MessageCatalogue('en');

        $m = new \ReflectionMethod($extractor, 'extractTemplate');
        $m->invoke($extractor, $template, $catalogue);

        if (0 === \count($messages)) {
            $this->assertSame($catalogue->all(), $messages);
        }

        foreach ($messages as $key => $domain) {
            $this->assertTrue($catalogue->has($key, $domain));
            $this->assertEquals('prefix'.$key, $catalogue->get($key, $domain));
        }
    }

    public static function getExtractData()
    {
        return [
            ['{{ "new key" | trans() }}', ['new key' => 'messages']],
            ['{{ "new key" | trans() | upper }}', ['new key' => 'messages']],
            ['{{ "new key" | trans({}, "domain") }}', ['new key' => 'domain']],
            ['{% trans %}new key{% endtrans %}', ['new key' => 'messages']],
            ['{% trans %}  new key  {% endtrans %}', ['new key' => 'messages']],
            ['{% trans from "domain" %}new key{% endtrans %}', ['new key' => 'domain']],
            ['{% set foo = "new key" | trans %}', ['new key' => 'messages']],
            ['{{ 1 ? "new key" | trans : "another key" | trans }}', ['new key' => 'messages', 'another key' => 'messages']],
            ['{{ t("new key") | trans() }}', ['new key' => 'messages']],
            ['{% set foo = t("new key") %}', ['new key' => 'messages']],
            ['{{ t("new key", {}, "domain") | trans() }}', ['new key' => 'domain']],
            ['{{ 1 ? t("new key") | trans : t("another key") | trans }}', ['new key' => 'messages', 'another key' => 'messages']],

            // make sure 'trans_default_domain' tag is supported
            ['{% trans_default_domain "domain" %}{{ "new key"|trans }}', ['new key' => 'domain']],
            ['{% trans_default_domain "domain" %}{% trans %}new key{% endtrans %}', ['new key' => 'domain']],

            // make sure this works with twig's named arguments
            ['{{ "new key" | trans(domain="domain") }}', ['new key' => 'domain']],

            // concat translations
            ['{{ ("new" ~ " key") | trans() }}', ['new key' => 'messages']],
            ['{{ ("another " ~ "new " ~ "key") | trans() }}', ['another new key' => 'messages']],
            ['{{ ("new" ~ " key") | trans(domain="domain") }}', ['new key' => 'domain']],
            ['{{ ("another " ~ "new " ~ "key") | trans(domain="domain") }}', ['another new key' => 'domain']],
            // if it has a variable or other expression, we cannot extract it
            ['{% set foo = "new" %} {{ ("new " ~ foo ~ "key") | trans() }}', []],
            ['{{ ("foo " ~ "new"|trans ~ "key") | trans() }}', ['new' => 'messages']],
        ];
    }

    /**
     * @dataProvider resourcesWithSyntaxErrorsProvider
     */
    public function testExtractSyntaxError($resources, array $messages)
    {
        $twig = new Environment($this->createMock(LoaderInterface::class));
        $twig->addExtension(new TranslationExtension($this->createMock(TranslatorInterface::class)));

        $extractor = new TwigExtractor($twig);
        $catalogue = new MessageCatalogue('en');
        $extractor->extract($resources, $catalogue);
        $this->assertSame($messages, $catalogue->all());
    }

    public static function resourcesWithSyntaxErrorsProvider(): array
    {
        return [
            [__DIR__.'/../Fixtures', ['messages' => ['Hi!' => 'Hi!']]],
            [__DIR__.'/../Fixtures/extractor/syntax_error.twig', []],
            [new \SplFileInfo(__DIR__.'/../Fixtures/extractor/syntax_error.twig'), []],
        ];
    }

    /**
     * @dataProvider resourceProvider
     */
    public function testExtractWithFiles($resource)
    {
        $loader = new ArrayLoader([]);
        $twig = new Environment($loader, [
            'strict_variables' => true,
            'debug' => true,
            'cache' => false,
            'autoescape' => false,
        ]);
        $twig->addExtension(new TranslationExtension($this->createMock(TranslatorInterface::class)));

        $extractor = new TwigExtractor($twig);
        $catalogue = new MessageCatalogue('en');
        $extractor->extract($resource, $catalogue);

        $this->assertTrue($catalogue->has('Hi!', 'messages'));
        $this->assertEquals('Hi!', $catalogue->get('Hi!', 'messages'));
    }

    public static function resourceProvider(): array
    {
        $directory = __DIR__.'/../Fixtures/extractor/';

        return [
            [$directory.'with_translations.html.twig'],
            [[$directory.'with_translations.html.twig']],
            [[new \SplFileInfo($directory.'with_translations.html.twig')]],
            [new \ArrayObject([$directory.'with_translations.html.twig'])],
            [new \ArrayObject([new \SplFileInfo($directory.'with_translations.html.twig')])],
        ];
    }
}
