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

use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageSelector;

class TranslatorCacheTest extends \PHPUnit_Framework_TestCase
{
    protected $tmpDir;

    protected function setUp()
    {
        $this->tmpDir = sys_get_temp_dir().'/sf2_translation';
        $this->deleteTmpDir();
    }

    protected function tearDown()
    {
        $this->deleteTmpDir();
    }

    protected function deleteTmpDir()
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

    public function testTransWithoutCaching()
    {
        $translator = $this->getTranslator($this->getLoader());
        $translator->setLocale('fr');
        $translator->setFallbackLocales(array('en', 'es', 'pt-PT', 'pt_BR', 'fr.UTF-8', 'sr@latin'));

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->transChoice('choice', 0));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->transChoice('other choice', 1));
        $this->assertEquals('foobarbaz (fr.UTF-8)', $translator->trans('foobarbaz'));
        $this->assertEquals('foobarbax (sr@latin)', $translator->trans('foobarbax'));
    }

    public function testTransWithCaching()
    {
        // prime the cache
        $translator = $this->getTranslator($this->getLoader(), $this->tmpDir);
        $translator->setLocale('fr');
        $translator->setFallbackLocales(array('en', 'es', 'pt-PT', 'pt_BR', 'fr.UTF-8', 'sr@latin'));

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->transChoice('choice', 0));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->transChoice('other choice', 1));
        $this->assertEquals('foobarbaz (fr.UTF-8)', $translator->trans('foobarbaz'));
        $this->assertEquals('foobarbax (sr@latin)', $translator->trans('foobarbax'));

        // do it another time as the cache is primed now
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $translator = $this->getTranslator($loader, $this->tmpDir);
        $translator->setLocale('fr');
        $translator->setFallbackLocales(array('en', 'es', 'pt-PT', 'pt_BR', 'fr.UTF-8', 'sr@latin'));

        $this->assertEquals('foo (FR)', $translator->trans('foo'));
        $this->assertEquals('bar (EN)', $translator->trans('bar'));
        $this->assertEquals('foobar (ES)', $translator->trans('foobar'));
        $this->assertEquals('choice 0 (EN)', $translator->transChoice('choice', 0));
        $this->assertEquals('no translation', $translator->trans('no translation'));
        $this->assertEquals('foobarfoo (PT-PT)', $translator->trans('foobarfoo'));
        $this->assertEquals('other choice 1 (PT-BR)', $translator->transChoice('other choice', 1));
        $this->assertEquals('foobarbaz (fr.UTF-8)', $translator->trans('foobarbaz'));
        $this->assertEquals('foobarbax (sr@latin)', $translator->trans('foobarbax'));
    }

    public function testRefreshCacheWhenResourcesChange()
    {
        // prime the cache
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $loader
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('fr', array(
                'foo' => 'foo A',
            ))))
        ;

        $translator = new Translator('fr', new MessageSelector(), $this->tmpDir, true);
        $translator->setLocale('fr');
        $translator->addLoader('loader', $loader);
        $translator->addResource('loader', 'foo', 'fr');

        $this->assertEquals('foo A', $translator->trans('foo'));

        // add a new resource to refresh the cache
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $loader
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('fr', array(
                'foo' => 'foo B',
            ))))
        ;

        $translator = new Translator('fr', new MessageSelector(), $this->tmpDir, true);
        $translator->setLocale('fr');
        $translator->addLoader('loader', $loader);
        $translator->addResource('loader', 'bar', 'fr');

        $this->assertEquals('foo B', $translator->trans('foo'));
    }

    public function testTransWithCachingWithInvalidLocale()
    {
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $translator = $this->getTranslator($loader, $this->tmpDir, 'Symfony\Component\Translation\Tests\TranslatorWithInvalidLocale');

        $translator->setLocale('invalid locale');

        try {
            $translator->trans('foo');
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertFalse(file_exists($this->tmpDir.'/catalogue.invalid locale.php'));
        }
    }

    public function testLoadCatalogueWithCachingWithInvalidLocale()
    {
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $translator = $this->getTranslator($loader, $this->tmpDir, 'Symfony\Component\Translation\Tests\TranslatorWithInvalidLocale');

        try {
            $translator->proxyLoadCatalogue('invalid locale');
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertFalse(file_exists($this->tmpDir.'/catalogue.invalid locale.php'));
        }
    }

    public function testDifferentCacheFilesAreUsedForDifferentSetsOfFallbackLocales()
    {
        /*
         * Because the cache file contains a catalogue including all of its fallback
         * catalogues (either "inlined" in Symfony 2.7 production or "standalone"),
         * we must take the active set of fallback locales into consideration when
         * loading a catalogue from the cache.
         */
        $translator = new Translator('a', null, $this->tmpDir);
        $translator->setFallbackLocales(array('b'));

        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foo (a)'), 'a');
        $translator->addResource('array', array('bar' => 'bar (b)'), 'b');

        $this->assertEquals('bar (b)', $translator->trans('bar'));

        // Remove fallback locale
        $translator->setFallbackLocales(array());
        $this->assertEquals('bar', $translator->trans('bar'));

        // Use a fresh translator with no fallback locales, result should be the same
        $translator = new Translator('a', null, $this->tmpDir);

        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foo (a)'), 'a');
        $translator->addResource('array', array('bar' => 'bar (b)'), 'b');

        $this->assertEquals('bar', $translator->trans('bar'));
    }

    public function testGetCatalogueBehavesConsistently()
    {
        /*
         * Create a translator that loads two catalogues for two different locales.
         * The catalogues contain distinct sets of messages.
         */
        $translator = new Translator('a', null, $this->tmpDir);
        $translator->setFallbackLocales(array('b'));

        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foo (a)'), 'a');
        $translator->addResource('array', array('foo' => 'foo (b)'), 'b');
        $translator->addResource('array', array('bar' => 'bar (b)'), 'b');

        $catalogue = $translator->getCatalogue('a');
        $this->assertFalse($catalogue->defines('bar')); // Sure, the "a" catalogue does not contain that message.

        $fallback = $catalogue->getFallbackCatalogue();
        $this->assertTrue($fallback->defines('foo')); // "foo" is present in "a" and "b"

        /*
         * Now, repeat the same test.
         * Behind the scenes, the cache is used. But that should not matter, right?
         */
        $translator = new Translator('a', null, $this->tmpDir);
        $translator->setFallbackLocales(array('b'));

        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foo (a)'), 'a');
        $translator->addResource('array', array('foo' => 'foo (b)'), 'b');
        $translator->addResource('array', array('bar' => 'bar (b)'), 'b');

        $catalogue = $translator->getCatalogue('a');
        $this->assertFalse($catalogue->defines('bar'));

        $fallback = $catalogue->getFallbackCatalogue();
        $this->assertTrue($fallback->defines('foo'));
    }

    public function testRefreshCacheWhenResourcesAreNoLongerFresh()
    {
        $resource = $this->getMock('Symfony\Component\Config\Resource\ResourceInterface');
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $resource->method('isFresh')->will($this->returnValue(false));
        $loader
            ->expects($this->exactly(2))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('fr', array(), array($resource))));

        // prime the cache
        $translator = new Translator('fr', null, $this->tmpDir, true);
        $translator->addLoader('loader', $loader);
        $translator->addResource('loader', 'foo', 'fr');
        $translator->trans('foo');

        // prime the cache second time
        $translator = new Translator('fr', null, $this->tmpDir, true);
        $translator->addLoader('loader', $loader);
        $translator->addResource('loader', 'foo', 'fr');
        $translator->trans('foo');
    }

    protected function getCatalogue($locale, $messages, $resources = array())
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

    protected function getLoader()
    {
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $loader
            ->expects($this->at(0))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('fr', array(
                'foo' => 'foo (FR)',
            ))))
        ;
        $loader
            ->expects($this->at(1))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('en', array(
                'foo' => 'foo (EN)',
                'bar' => 'bar (EN)',
                'choice' => '{0} choice 0 (EN)|{1} choice 1 (EN)|]1,Inf] choice inf (EN)',
            ))))
        ;
        $loader
            ->expects($this->at(2))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('es', array(
                'foobar' => 'foobar (ES)',
            ))))
        ;
        $loader
            ->expects($this->at(3))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('pt-PT', array(
                'foobarfoo' => 'foobarfoo (PT-PT)',
            ))))
        ;
        $loader
            ->expects($this->at(4))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('pt_BR', array(
                'other choice' => '{0} other choice 0 (PT-BR)|{1} other choice 1 (PT-BR)|]1,Inf] other choice inf (PT-BR)',
            ))))
        ;
        $loader
            ->expects($this->at(5))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('fr.UTF-8', array(
                'foobarbaz' => 'foobarbaz (fr.UTF-8)',
            ))))
        ;
        $loader
            ->expects($this->at(6))
            ->method('load')
            ->will($this->returnValue($this->getCatalogue('sr@latin', array(
                'foobarbax' => 'foobarbax (sr@latin)',
            ))))
        ;

        return $loader;
    }

    public function getTranslator($loader, $cacheDir = null, $translatorClass = '\Symfony\Component\Translation\Translator')
    {
        $translator = new $translatorClass('fr', new MessageSelector(), $cacheDir);

        $translator->addLoader('loader', $loader);
        $translator->addResource('loader', 'foo', 'fr');
        $translator->addResource('loader', 'foo', 'en');
        $translator->addResource('loader', 'foo', 'es');
        $translator->addResource('loader', 'foo', 'pt-PT'); // European Portuguese
        $translator->addResource('loader', 'foo', 'pt_BR'); // Brazilian Portuguese
        $translator->addResource('loader', 'foo', 'fr.UTF-8');
        $translator->addResource('loader', 'foo', 'sr@latin'); // Latin Serbian

        return $translator;
    }
}

class TranslatorWithInvalidLocale extends Translator
{
    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    public function proxyLoadCatalogue($locale)
    {
        $this->loadCatalogue($locale);
    }
}
