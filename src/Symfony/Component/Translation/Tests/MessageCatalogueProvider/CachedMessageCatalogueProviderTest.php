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

use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueProvider\CachedMessageCatalogueProvider;
use Symfony\Component\Translation\MessageCatalogueProvider\ResourceMessageCatalogueProvider;
use Symfony\Component\Config\ConfigCacheFactory;

class CachedMessageCatalogueProviderTest extends \PHPUnit_Framework_TestCase
{
    private $tmpDir;

    protected function setUp()
    {
        $this->tmpDir = sys_get_temp_dir().'/sf2_translation';
        $this->deleteTmpDir();
    }

    protected function tearDown()
    {
        $this->deleteTmpDir();
    }

    /**
     * @dataProvider runForDebugAndProduction
     */
    public function testDifferentTranslatorsForSameLocaleDoNotOverwriteEachOthersCache($debug)
    {
        /*
         * Similar to the previous test. After we used the second translator, make
         * sure there's still a useable cache for the first one.
         */

        $locale = 'any_locale';
        $format = 'some_format';
        $msgid = 'test';

        // Create a Translator and prime its cache
        $messageCatalogueProvider = $this->getMessageCatalogueProvider($debug, array($format => new ArrayLoader()), array(array($format, array($msgid => 'OK'), $locale)));
        $messageCatalogueProvider->getCatalogue($locale);

        // Create another Translator with a different catalogue for the same locale
        $messageCatalogueProvider = $this->getMessageCatalogueProvider($debug, array($format => new ArrayLoader()), array(array($format, array($msgid => 'FAIL'), $locale)));
        $messageCatalogueProvider->getCatalogue($locale);

        // Now the first translator must still have a useable cache.
        $messageCatalogueProvider = $this->getMessageCatalogueProvider($debug, array($format => $this->createFailingLoader()), array(array($format, array($msgid => 'OK'), $locale)));
        $catalogue = $messageCatalogueProvider->getCatalogue($locale);
        $this->assertEquals('OK', $catalogue->get($msgid), '-> the cache was overwritten by another translator instance in '.($debug ? 'debug' : 'production'));
    }

    public function testPrimaryAndFallbackCataloguesContainTheSameMessagesRegardlessOfCaching()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('foo' => 'foo (a)'), 'a'),
            array('array', array('foo' => 'foo (b)'), 'b'),
            array('array', array('bar' => 'bar (b)'), 'b'),
        );

        /*
         * As a safeguard against potential BC breaks, make sure that primary and fallback
         * catalogues (reachable via getFallbackCatalogue()) always contain the full set of
         * messages provided by the loader. This must also be the case when these catalogues
         * are (internally) read from a cache.
         *
         * Optimizations inside the translator must not change this behaviour.
         */

        /*
         * Create a translator that loads two catalogues for two different locales.
         * The catalogues contain distinct sets of messages.
         */
        $messageCatalogueProvider = $this->getMessageCatalogueProvider(false, $loaders, $resources, array('b'));

        $catalogue = $messageCatalogueProvider->getCatalogue('a');
        $this->assertFalse($catalogue->defines('bar')); // Sure, the "a" catalogue does not contain that message.

        $fallback = $catalogue->getFallbackCatalogue();
        $this->assertTrue($fallback->defines('foo')); // "foo" is present in "a" and "b"

        /*
         * Now, repeat the same test.
         * Behind the scenes, the cache is used. But that should not matter, right?
         */
        $messageCatalogueProvider = $this->getMessageCatalogueProvider(false, $loaders, $resources, array('b'));

        $catalogue = $messageCatalogueProvider->getCatalogue('a');
        $this->assertFalse($catalogue->defines('bar'));

        $fallback = $catalogue->getFallbackCatalogue();
        $this->assertTrue($fallback->defines('foo'));
    }

    public function testDifferentCacheFilesAreUsedForDifferentSetsOfFallbackLocales()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('foo' => 'foo (a)'), 'a'),
            array('array', array('bar' => 'bar (b)'), 'b'),
        );

        /*
         * Because the cache file contains a catalogue including all of its fallback
         * catalogues, we must take the set of fallback locales into consideration when
         * loading a catalogue from the cache.
         */
        $messageCatalogueProvider = $this->getMessageCatalogueProvider(false, $loaders, $resources, array('b'));
        $catalogue = $messageCatalogueProvider->getCatalogue('a');
        $this->assertEquals('bar (b)', $catalogue->get('bar'));

        // Use a fresh translator with no fallback locales, result should be the same
        $messageCatalogueProvider = $this->getMessageCatalogueProvider(false, $loaders, $resources);
        $catalogue = $messageCatalogueProvider->getCatalogue('a');
        $this->assertEquals('bar', $catalogue->get('bar'));
    }

    public function testRefreshCacheWhenResourcesAreNoLongerFresh()
    {
        $resource = $this->getMock('Symfony\Component\Config\Resource\SelfCheckingResourceInterface');
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $resource->method('isFresh')->will($this->returnValue(false));
        $loader
            ->expects($this->exactly(2))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('fr', array(), array($resource))));

        // prime the cache
        $messageCatalogueProvider = $this->getMessageCatalogueProvider(true, array('loader' => $loader), array(array('loader', 'foo', 'fr')));
        $messageCatalogueProvider->getCatalogue('fr');

        // prime the cache second time
        $messageCatalogueProvider = $this->getMessageCatalogueProvider(true, array('loader' => $loader), array(array('loader', 'foo', 'fr')));
        $messageCatalogueProvider->getCatalogue('fr');
    }

    public function runForDebugAndProduction()
    {
        return array(array(true), array(false));
    }

    /**
     * @return LoaderInterface
     */
    private function createFailingLoader()
    {
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $loader
            ->expects($this->never())
            ->method('load');

        return $loader;
    }

    protected function getMessageCatalogueProvider($debug, $loaders = array(), $resources = array(), $fallbackLocales = array())
    {
        $resourceCatalogue = new ResourceMessageCatalogueProvider($loaders, $resources, $fallbackLocales);

        return new CachedMessageCatalogueProvider($resourceCatalogue, new ConfigCacheFactory($debug), $this->tmpDir);
    }

    private function getCatalogue($locale, $messages, $resources = array())
    {
        $catalogue = new MessageCatalogue($locale);
        foreach ($messages as $key => $translation) {
            $catalogue->set($key, $translation);
        }
        foreach ($resources as $resource) {
            $catalogue->addResource($resource);
        }

        return $catalogue;
    }

    private function deleteTmpDir()
    {
        if (!file_exists($dir = $this->tmpDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tmpDir), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $path) {
            if (preg_match('#[/\\\\]\.\.?$#', $path->__toString())) {
                continue;
            }
            if ($path->isDir()) {
                rmdir($path->__toString());
            } else {
                unlink($path->__toString());
            }
        }
        rmdir($this->tmpDir);
    }
}

class StaleResource implements SelfCheckingResourceInterface
{
    public function isFresh($timestamp)
    {
        return false;
    }

    public function getResource()
    {
    }

    public function __toString()
    {
        return '';
    }
}
