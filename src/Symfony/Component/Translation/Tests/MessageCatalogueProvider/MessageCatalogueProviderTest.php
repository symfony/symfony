<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\MessageCatalogueProvider\Tests;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueProvider\MessageCatalogueProvider;
use Symfony\Component\Translation\Loader\ArrayLoader;

class MessageCatalogueProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \InvalidArgumentException
     */
    public function testAddResourceInvalidLocales($locale)
    {
        $translatorBag = $this->getMessageCatalogueProvider();
        $translatorBag->addResource('array', array('foo' => 'foofoo'), $locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testAddResourceValidLocales($locale)
    {
        $translatorBag = $this->getMessageCatalogueProvider();
        $translatorBag->addResource('array', array('foo' => 'foofoo'), $locale);
        // no assertion. this method just asserts that no exception is thrown
    }

    public function testGetCatalogue()
    {
        $translatorBag = $this->getMessageCatalogueProvider();
        $this->assertEquals(new MessageCatalogue('en'), $translatorBag->getCatalogue('en'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testWhenAResourceHasNoRegisteredLoader()
    {
        $translatorBag = $this->getMessageCatalogueProvider();
        $translatorBag->addResource('array', array('foo' => 'foofoo'), 'en');

        $translatorBag->getCatalogue('en');
    }

    /**
     * @dataProvider      getTransFileTests
     * @expectedException \Symfony\Component\Translation\Exception\NotFoundResourceException
     */
    public function testLoadLocaleFile($format, $loader)
    {
        $loaderClass = 'Symfony\\Component\\Translation\\Loader\\'.$loader;
        $loaders = array($format => new $loaderClass());
        $resources = array(
            array($format, __DIR__.'/fixtures/non-existing', 'en'),
            array($format, __DIR__.'/fixtures/resources.'.$format, 'en'),
        );

        $translatorBag = $this->getMessageCatalogueProvider($loaders, $resources);

        // force catalogue loading
        $translatorBag->getCatalogue('en');
    }

    public function testSetFallbackLocales()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('foo' => 'foofoo'), 'en'),
            array('array', array('bar' => 'foobar'), 'fr'),
        );

        // load catalogue
        $translatorBag = $this->getMessageCatalogueProvider($loaders, $resources, array());
        $translatorBag->setFallbackLocales(array('fr'));

        $catalogue = $translatorBag->getCatalogue('en');
        $this->assertEquals('foobar', $catalogue->get('bar'));
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \InvalidArgumentException
     */
    public function testSetFallbackInvalidLocales($locale)
    {
        $this->getMessageCatalogueProvider(array(), array(), array($locale));
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testSetFallbackValidLocales($locale)
    {
        $this->getMessageCatalogueProvider(array(), array(), array($locale));
        // no assertion. this method just asserts that no exception is thrown
    }

    public function testLoadCatalogueWithFallbackLocale()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('bar' => 'foobar'), 'en'),
        );
        $translatorBag = $this->getMessageCatalogueProvider($loaders, $resources, array('en'));

        // load catalogue
        $catalogue = $translatorBag->getCatalogue('fr_FR');

        $this->assertEquals('foobar', $catalogue->get('bar'));
    }

    private function getMessageCatalogueProvider($loaders = array(), $resources = array(), $fallbacklocales = array())
    {
        return new MessageCatalogueProvider($loaders, $resources, $fallbacklocales);
    }

    public function getInvalidLocalesTests()
    {
        return array(
            array('fr FR'),
            array('français'),
            array('fr+en'),
            array('utf#8'),
            array('fr&en'),
            array('fr~FR'),
            array(' fr'),
            array('fr '),
            array('fr*'),
            array('fr/FR'),
            array('fr\\FR'),
        );
    }

    public function getValidLocalesTests()
    {
        return array(
            array(''),
            array(null),
            array('fr'),
            array('francais'),
            array('FR'),
            array('frFR'),
            array('fr-FR'),
            array('fr_FR'),
            array('fr.FR'),
            array('fr-FR.UTF8'),
            array('sr@latin'),
        );
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
}
