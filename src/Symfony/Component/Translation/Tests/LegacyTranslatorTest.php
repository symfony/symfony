<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests;

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;

/**
 * @group legacy
 */
class LegacyTranslatorTest extends TranslatorTest
{
    public function testSetFallbackLocales()
    {
        $resources = array(
            array('array', array('foo' => 'foofoo'), 'en'),
            array('array', array('bar' => 'foobar'), 'fr'),
        );
        $translator = $this->getTranslator('en', array('array' => new ArrayLoader()), $resources);

        // force catalogue loading
        $translator->trans('bar');

        $translator->setFallbackLocales(array('fr'));
        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    public function testSetFallbackLocalesMultiple()
    {
        $resources = array(
            array('array', array('foo' => 'foo (en)'), 'en'),
            array('array', array('bar' => 'bar (fr)'), 'fr'),
        );
        $translator = $this->getTranslator('en', array('array' => new ArrayLoader()), $resources);

        // force catalogue loading
        $translator->trans('bar');

        $translator->setFallbackLocales(array('fr_FR', 'fr'));
        $this->assertEquals('bar (fr)', $translator->trans('bar'));
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \InvalidArgumentException
     */
    public function testSetFallbackInvalidLocales($locale)
    {
        $this->getTranslator('fr', array(), array(), array('fr', $locale));
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testSetFallbackValidLocales($locale)
    {
        $this->getTranslator('fr_FR', array(), array(), array('fr', $locale));
        // no assertion. this method just asserts that no exception is thrown
    }

    public function testTransWithFallbackLocale()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('bar' => 'foobar'), 'en'),
        );
        $translator = $this->getTranslator('fr_FR', $loaders, $resources, array('en'));

        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \InvalidArgumentException
     */
    public function testAddResourceInvalidLocales($locale)
    {
        $this->getTranslator('fr', array(), array(array('array', array('foo' => 'foofoo'), $locale)));
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testAddResourceValidLocales($locale)
    {
        $this->getTranslator('fr', array(), array(array('array', array('foo' => 'foofoo'), $locale)));
        // no assertion. this method just asserts that no exception is thrown
    }

    public function testAddResourceAfterTrans()
    {
        $translator = $this->getTranslator('en', array('array' => new ArrayLoader()));

        $translator->addResource('array', array('foo' => 'foofoo'), 'en');
        $this->assertEquals('foofoo', $translator->trans('foo'));

        $translator->addResource('array', array('bar' => 'foobar'), 'en');
        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    /**
     * @dataProvider      getTransFileTests
     * @expectedException \Symfony\Component\Translation\Exception\NotFoundResourceException
     */
    public function testTransWithoutFallbackLocaleFile($format, $loader)
    {
        $loaderClass = 'Symfony\\Component\\Translation\\Loader\\'.$loader;

        $loaders = array($format => new $loaderClass());
        $resources = array(
            array($format, __DIR__.'/fixtures/non-existing', 'en'),
            array($format, __DIR__.'/fixtures/resources.'.$format, 'en'),
        );
        $translator = $this->getTranslator('en', $loaders, $resources);

        // force catalogue loading
        $translator->trans('foo');
    }

    /**
     * @dataProvider getTransFileTests
     */
    public function testTransWithFallbackLocaleFile($format, $loader)
    {
        $loaderClass = 'Symfony\\Component\\Translation\\Loader\\'.$loader;
        $loaders = array($format => new $loaderClass());
        $resources = array(
            array($format, __DIR__.'/fixtures/resources.'.$format, 'en', 'resources'),
        );
        $translator = $this->getTranslator('en_GB', $loaders, $resources);

        $this->assertEquals('bar', $translator->trans('foo', array(), 'resources'));
    }

    public function testTransWithFallbackLocaleBis()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('foo' => 'foofoo'), 'en_US'),
            array('array', array('bar' => 'foobar'), 'en'),
        );
        $translator = $this->getTranslator('en_US', $loaders, $resources);

        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    public function testTransWithFallbackLocaleTer()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('foo' => 'foo (en_US)'), 'en_US'),
            array('array', array('bar' => 'bar (en)'), 'en'),
        );
        $translator = $this->getTranslator('fr_FR', $loaders, $resources, array('en_US', 'en'));

        $this->assertEquals('foo (en_US)', $translator->trans('foo'));
        $this->assertEquals('bar (en)', $translator->trans('bar'));
    }

    public function testTransNonExistentWithFallback()
    {
        $loaders = array('array' => new ArrayLoader());
        $translator = $this->getTranslator('fr', $loaders, array(), array('en'));

        $this->assertEquals('non-existent', $translator->trans('non-existent'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testWhenAResourceHasNoRegisteredLoader()
    {
        $resources = array(array('array', array('foo' => 'foofoo'), 'en'));
        $translator = $this->getTranslator('en', array(), $resources);

        $translator->trans('foo');
    }

    protected function getTranslator($locale, $loaders = array(), $resources = array(), $fallbackLocales = array())
    {
        $translator = new Translator($locale);
        $translator->setFallbackLocales($fallbackLocales);
        foreach ($loaders as $format => $loader) {
            $translator->addLoader($format, $loader);
        }

        foreach ($resources as $resource) {
            $translator->addResource($resource[0], $resource[1], $resource[2], isset($resource[3]) ? $resource[3] : null);
        }

        return $translator;
    }

    /**
     * @group legacy
     * @dataProvider dataProviderGetMessages
     */
    public function testLegacyGetMessages($resources, $locale, $expected)
    {
        $locales = array_keys($resources);
        $_locale = !is_null($locale) ? $locale : reset($locales);
        $locales = array_slice($locales, 0, array_search($_locale, $locales));

        $translator = new Translator($_locale, new MessageSelector());
        $translator->setFallbackLocales(array_reverse($locales));
        $translator->addLoader('array', new ArrayLoader());
        foreach ($resources as $_locale => $domainMessages) {
            foreach ($domainMessages as $domain => $messages) {
                $translator->addResource('array', $messages, $_locale, $domain);
            }
        }
        $result = $translator->getMessages($locale);

        $this->assertEquals($expected, $result);
    }

    public function getTransFileTests()
    {
        return array(
            array('csv', 'CsvFileLoader'),
            array('ini', 'IniFileLoader'),
            array('mo', 'MoFileLoader'),
            array('po', 'PoFileLoader'),
            array('php', 'PhpFileLoader'),
            array('ts', 'QtFileLoader'),
            array('xlf', 'XliffFileLoader'),
            array('yml', 'YamlFileLoader'),
            array('json', 'JsonFileLoader'),
        );
    }

    public function dataProviderGetMessages()
    {
        $resources = array(
            'en' => array(
                'jsmessages' => array(
                    'foo' => 'foo (EN)',
                    'bar' => 'bar (EN)',
                ),
                'messages' => array(
                    'foo' => 'foo messages (EN)',
                ),
                'validators' => array(
                    'int' => 'integer (EN)',
                ),
            ),
            'pt-PT' => array(
                'messages' => array(
                    'foo' => 'foo messages (PT)',
                ),
                'validators' => array(
                    'str' => 'integer (PT)',
                ),
            ),
            'pt_BR' => array(
                'validators' => array(
                    'int' => 'integer (BR)',
                ),
            ),
        );

        return array(
            array($resources, null,
                array(
                    'jsmessages' => array(
                        'foo' => 'foo (EN)',
                        'bar' => 'bar (EN)',
                    ),
                    'messages' => array(
                        'foo' => 'foo messages (EN)',
                    ),
                    'validators' => array(
                        'int' => 'integer (EN)',
                    ),
                ),
            ),
            array($resources, 'en',
                array(
                    'jsmessages' => array(
                        'foo' => 'foo (EN)',
                        'bar' => 'bar (EN)',
                    ),
                    'messages' => array(
                        'foo' => 'foo messages (EN)',
                    ),
                    'validators' => array(
                        'int' => 'integer (EN)',
                    ),
                ),
            ),
            array($resources, 'pt-PT',
                array(
                    'jsmessages' => array(
                        'foo' => 'foo (EN)',
                        'bar' => 'bar (EN)',
                    ),
                    'messages' => array(
                        'foo' => 'foo messages (PT)',
                    ),
                    'validators' => array(
                        'int' => 'integer (EN)',
                        'str' => 'integer (PT)',
                    ),
                ),
            ),
            array($resources, 'pt_BR',
                array(
                    'jsmessages' => array(
                        'foo' => 'foo (EN)',
                        'bar' => 'bar (EN)',
                    ),
                    'messages' => array(
                        'foo' => 'foo messages (PT)',
                    ),
                    'validators' => array(
                        'int' => 'integer (BR)',
                        'str' => 'integer (PT)',
                    ),
                ),
            ),
        );
    }
}
