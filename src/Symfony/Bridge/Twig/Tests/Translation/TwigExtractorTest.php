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

use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Translation\TwigExtractor;
use Symfony\Component\Translation\MessageCatalogue;

class TwigExtractorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getExtractData
     */
    public function testExtract($template, $messages)
    {
        $loader = $this->getMock('Twig_LoaderInterface');
        $twig = new \Twig_Environment($loader, array(
            'strict_variables' => true,
            'debug' => true,
            'cache' => false,
            'autoescape' => false,
        ));
        $twig->addExtension(new TranslationExtension($this->getMock('Symfony\Component\Translation\TranslatorInterface')));

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

    public function getExtractData()
    {
        return array(
            array('{{ "new key" | trans() }}', array('new key' => 'messages')),
            array('{{ "new key" | trans() | upper }}', array('new key' => 'messages')),
            array('{{ "new key" | trans({}, "domain") }}', array('new key' => 'domain')),
            array('{{ "new key" | transchoice(1) }}', array('new key' => 'messages')),
            array('{{ "new key" | transchoice(1) | upper }}', array('new key' => 'messages')),
            array('{{ "new key" | transchoice(1, {}, "domain") }}', array('new key' => 'domain')),
            array('{% trans %}new key{% endtrans %}', array('new key' => 'messages')),
            array('{% trans %}  new key  {% endtrans %}', array('new key' => 'messages')),
            array('{% trans from "domain" %}new key{% endtrans %}', array('new key' => 'domain')),
            array('{% set foo = "new key" | trans %}', array('new key' => 'messages')),
            array('{{ 1 ? "new key" | trans : "another key" | trans }}', array('new key' => 'messages', 'another key' => 'messages')),

            // make sure 'trans_default_domain' tag is supported
            array('{% trans_default_domain "domain" %}{{ "new key"|trans }}', array('new key' => 'domain')),
            array('{% trans_default_domain "domain" %}{{ "new key"|transchoice }}', array('new key' => 'domain')),
            array('{% trans_default_domain "domain" %}{% trans %}new key{% endtrans %}', array('new key' => 'domain')),

            // make sure this works with twig's named arguments
            array('{{ "new key" | trans(domain="domain") }}', array('new key' => 'domain')),
            array('{{ "new key" | transchoice(domain="domain", count=1) }}', array('new key' => 'domain')),
        );
    }

    /**
     * @expectedException \Twig_Error
     * @dataProvider resourcesWithSyntaxErrorsProvider
     */
    public function testExtractSyntaxError($resources)
    {
        $twig = new \Twig_Environment($this->getMock('Twig_LoaderInterface'));
        $twig->addExtension(new TranslationExtension($this->getMock('Symfony\Component\Translation\TranslatorInterface')));

        $extractor = new TwigExtractor($twig);

        try {
            $extractor->extract($resources, new MessageCatalogue('en'));
        } catch (\Twig_Error $e) {
            if (method_exists($e, 'getSourceContext')) {
                $this->assertSame(dirname(__DIR__).strtr('/Fixtures/extractor/syntax_error.twig', '/', DIRECTORY_SEPARATOR), $e->getFile());
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
        return array(
            array(__DIR__.'/../Fixtures'),
            array(__DIR__.'/../Fixtures/extractor/syntax_error.twig'),
            array(new \SplFileInfo(__DIR__.'/../Fixtures/extractor/syntax_error.twig')),
        );
    }

    /**
     * @dataProvider resourceProvider
     */
    public function testExtractWithFiles($resource)
    {
        $loader = new \Twig_Loader_Array(array());
        $twig = new \Twig_Environment($loader, array(
            'strict_variables' => true,
            'debug' => true,
            'cache' => false,
            'autoescape' => false,
        ));
        $twig->addExtension(new TranslationExtension($this->getMock('Symfony\Component\Translation\TranslatorInterface')));

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

        return array(
            array($directory.'with_translations.html.twig'),
            array(array($directory.'with_translations.html.twig')),
            array(array(new \SplFileInfo($directory.'with_translations.html.twig'))),
            array(new \ArrayObject(array($directory.'with_translations.html.twig'))),
            array(new \ArrayObject(array(new \SplFileInfo($directory.'with_translations.html.twig')))),
        );
    }
}
