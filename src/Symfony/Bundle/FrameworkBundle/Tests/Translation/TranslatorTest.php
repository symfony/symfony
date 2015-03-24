<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Translation;

use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\MessageSelector;

class TranslatorTest extends \PHPUnit_Framework_TestCase
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

        $fs = new Filesystem();
        $fs->remove($dir);
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
        $translator = $this->getTranslator($this->getLoader(), array('cache_dir' => $this->tmpDir));
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
        $translator = $this->getTranslator($loader, array('cache_dir' => $this->tmpDir));
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

    public function testTransWithCachingWithInvalidLocale()
    {
        $loader = $this->getMock('Symfony\Component\Translation\Loader\LoaderInterface');
        $translator = $this->getTranslator($loader, array('cache_dir' => $this->tmpDir), array(), 'loader', '\Symfony\Bundle\FrameworkBundle\Tests\Translation\TranslatorWithInvalidLocale');
        $translator->setLocale('invalid locale');

        $this->setExpectedException('\InvalidArgumentException');
        $translator->trans('foo');
    }

    public function testLoadRessourcesWithCaching()
    {
        $loader = new \Symfony\Component\Translation\Loader\YamlFileLoader();
        $resourceFiles = array(
            'fr' => array(
                __DIR__.'/../Fixtures/Resources/translations/messages.fr.yml',
            ),
        );

        // prime the cache
        $translator = $this->getTranslator($loader, array('cache_dir' => $this->tmpDir), $resourceFiles, 'yml');
        $translator->setLocale('fr');

        $this->assertEquals('répertoire', $translator->trans('folder'));

        // do it another time as the cache is primed now
        $translator = $this->getTranslator($loader, array('cache_dir' => $this->tmpDir), array(), 'yml');
        $translator->setLocale('fr');

        $this->assertEquals('répertoire', $translator->trans('folder'));
    }

    public function testLoadRessourcesWithoutCaching()
    {
        $loader = new \Symfony\Component\Translation\Loader\YamlFileLoader();
        $resourceFiles = array(
            'fr' => array(
                __DIR__.'/../Fixtures/Resources/translations/messages.fr.yml',
            ),
        );

        $translator = $this->getTranslator($loader, array(), $resourceFiles, 'yml');
        $translator->setLocale('fr');

        $this->assertEquals('répertoire', $translator->trans('folder'));
    }

    protected function getCatalogue($locale, $messages)
    {
        $catalogue = new MessageCatalogue($locale);
        foreach ($messages as $key => $translation) {
            $catalogue->set($key, $translation);
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

    protected function getContainer($loader)
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValue($loader))
        ;

        return $container;
    }

    public function getTranslator($loader, $options = array(), $resources = array(), $loaderFomat = 'loader', $translatorClass = '\Symfony\Bundle\FrameworkBundle\Translation\Translator')
    {
        $translator = new $translatorClass(
            $this->getContainer($loader),
            new MessageSelector(),
            array($loaderFomat => array($loaderFomat)),
            $options,
            $resources
        );

        if ('loader' === $loaderFomat) {
            $translator->addResource('loader', 'foo', 'fr');
            $translator->addResource('loader', 'foo', 'en');
            $translator->addResource('loader', 'foo', 'es');
            $translator->addResource('loader', 'foo', 'pt-PT'); // European Portuguese
            $translator->addResource('loader', 'foo', 'pt_BR'); // Brazilian Portuguese
            $translator->addResource('loader', 'foo', 'fr.UTF-8');
            $translator->addResource('loader', 'foo', 'sr@latin'); // Latin Serbian
        }

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
}
