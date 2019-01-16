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
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;

class TwigExtractorTest extends TestCase
{
    /**
     * @dataProvider getExtractData
     */
    public function testExtract($template, $messages)
    {
        $loader = $this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock();
        $twig = new Environment($loader, [
            'strict_variables' => true,
            'debug' => true,
            'cache' => false,
            'autoescape' => false,
        ]);
        $twig->addExtension(new TranslationExtension($this->getMockBuilder(TranslatorInterface::class)->getMock()));

        $extractor = new TwigExtractor($twig);
        $extractor->setPrefix('prefix');
        $catalogue = new MessageCatalogue('en');

        $m = new \ReflectionMethod($extractor, 'extractTemplate');
        $m->setAccessible(true);
        $m->invoke($extractor, $template, $catalogue);

        foreach ($messages as $key => $domain) {
            $this->assertTrue($catalogue->has($key, $domain));
            $this->assertEquals('prefix'.$key, $catalogue->get($key, $domain));
        }
    }

    /**
     * @group legacy
     * @dataProvider getLegacyExtractData
     */
    public function testLegacyExtract($template, $messages)
    {
        $this->testExtract($template, $messages);
    }

    public function getExtractData()
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

            // make sure 'trans_default_domain' tag is supported
            ['{% trans_default_domain "domain" %}{{ "new key"|trans }}', ['new key' => 'domain']],
            ['{% trans_default_domain "domain" %}{% trans %}new key{% endtrans %}', ['new key' => 'domain']],

            // make sure this works with twig's named arguments
            ['{{ "new key" | trans(domain="domain") }}', ['new key' => 'domain']],
        ];
    }

    /**
     * @group legacy
     */
    public function getLegacyExtractData()
    {
        return [
            ['{{ "new key" | transchoice(1) }}', ['new key' => 'messages']],
            ['{{ "new key" | transchoice(1) | upper }}', ['new key' => 'messages']],
            ['{{ "new key" | transchoice(1, {}, "domain") }}', ['new key' => 'domain']],

            // make sure 'trans_default_domain' tag is supported
            ['{% trans_default_domain "domain" %}{{ "new key"|transchoice }}', ['new key' => 'domain']],

            // make sure this works with twig's named arguments
            ['{{ "new key" | transchoice(domain="domain", count=1) }}', ['new key' => 'domain']],
        ];
    }

    /**
     * @expectedException \Twig\Error\Error
     * @dataProvider resourcesWithSyntaxErrorsProvider
     */
    public function testExtractSyntaxError($resources)
    {
        $twig = new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock());
        $twig->addExtension(new TranslationExtension($this->getMockBuilder(TranslatorInterface::class)->getMock()));

        $extractor = new TwigExtractor($twig);

        try {
            $extractor->extract($resources, new MessageCatalogue('en'));
        } catch (Error $e) {
            if (method_exists($e, 'getSourceContext')) {
                $this->assertSame(\dirname(__DIR__).strtr('/Fixtures/extractor/syntax_error.twig', '/', \DIRECTORY_SEPARATOR), $e->getFile());
                $this->assertSame(1, $e->getLine());
                $this->assertSame('Unclosed "block".', $e->getMessage());
            } else {
                $this->expectExceptionMessageRegExp('/Unclosed "block" in ".*extractor(\\/|\\\\)syntax_error\\.twig" at line 1/');
            }
            throw $e;
        }
    }

    /**
     * @return array
     */
    public function resourcesWithSyntaxErrorsProvider()
    {
        return [
            [__DIR__.'/../Fixtures'],
            [__DIR__.'/../Fixtures/extractor/syntax_error.twig'],
            [new \SplFileInfo(__DIR__.'/../Fixtures/extractor/syntax_error.twig')],
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
        $twig->addExtension(new TranslationExtension($this->getMockBuilder(TranslatorInterface::class)->getMock()));

        $extractor = new TwigExtractor($twig);
        $catalogue = new MessageCatalogue('en');
        $extractor->extract($resource, $catalogue);

        $this->assertTrue($catalogue->has('Hi!', 'messages'));
        $this->assertEquals('Hi!', $catalogue->get('Hi!', 'messages'));
    }

    /**
     * @return array
     */
    public function resourceProvider()
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
