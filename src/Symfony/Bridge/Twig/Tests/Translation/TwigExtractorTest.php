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
use Symfony\Bridge\Twig\Tests\TestCase;

class TwigExtractorTest extends TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Translation\Translator')) {
            $this->markTestSkipped('The "Translation" component is not available');
        }
    }

    /**
     * @dataProvider getExtractData
     */
    public function testExtract($template, $messages)
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
}
