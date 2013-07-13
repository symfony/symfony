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

class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    public function testSetGetLocale()
    {
        $translator = new Translator('en', new MessageSelector());

        $this->assertEquals('en', $translator->getLocale());

        $translator->setLocale('fr');
        $this->assertEquals('fr', $translator->getLocale());
    }

    public function testSetFallbackLocales()
    {
        $translator = new Translator('en', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foofoo'), 'en');
        $translator->addResource('array', array('bar' => 'foobar'), 'fr');

        // force catalogue loading
        $translator->trans('bar');

        $translator->setFallbackLocales(array('fr'));
        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    public function testSetFallbackLocalesMultiple()
    {
        $translator = new Translator('en', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foo (en)'), 'en');
        $translator->addResource('array', array('bar' => 'bar (fr)'), 'fr');

        // force catalogue loading
        $translator->trans('bar');

        $translator->setFallbackLocales(array('fr_FR', 'fr'));
        $this->assertEquals('bar (fr)', $translator->trans('bar'));
    }

    public function testTransWithFallbackLocale()
    {
        $translator = new Translator('fr_FR', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foofoo'), 'en_US');
        $translator->addResource('array', array('bar' => 'foobar'), 'en');

        $translator->setFallbackLocales(array('en'));

        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    public function testAddResourceAfterTrans()
    {
        $translator = new Translator('fr', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());

        $translator->setFallbackLocale(array('en'));

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
        $translator = new Translator('en', new MessageSelector());
        $translator->addLoader($format, new $loaderClass());
        $translator->addResource($format, __DIR__.'/fixtures/non-existing', 'en');
        $translator->addResource($format, __DIR__.'/fixtures/resources.'.$format, 'en');

        // force catalogue loading
        $translator->trans('foo');
    }

    /**
     * @dataProvider getTransFileTests
     */
    public function testTransWithFallbackLocaleFile($format, $loader)
    {
        $loaderClass = 'Symfony\\Component\\Translation\\Loader\\'.$loader;
        $translator = new Translator('en_GB', new MessageSelector());
        $translator->addLoader($format, new $loaderClass());
        $translator->addResource($format, __DIR__.'/fixtures/non-existing', 'en_GB');
        $translator->addResource($format, __DIR__.'/fixtures/resources.'.$format, 'en', 'resources');

        $this->assertEquals('bar', $translator->trans('foo', array(), 'resources'));
    }

    public function testTransWithFallbackLocaleBis()
    {
        $translator = new Translator('en_US', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foofoo'), 'en_US');
        $translator->addResource('array', array('bar' => 'foobar'), 'en');
        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    public function testTransWithFallbackLocaleTer()
    {
        $translator = new Translator('fr_FR', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foo (en_US)'), 'en_US');
        $translator->addResource('array', array('bar' => 'bar (en)'), 'en');

        $translator->setFallbackLocales(array('en_US', 'en'));

        $this->assertEquals('foo (en_US)', $translator->trans('foo'));
        $this->assertEquals('bar (en)', $translator->trans('bar'));
    }

    public function testTransNonExistentWithFallback()
    {
        $translator = new Translator('fr', new MessageSelector());
        $translator->setFallbackLocales(array('en'));
        $translator->addLoader('array', new ArrayLoader());
        $this->assertEquals('non-existent', $translator->trans('non-existent'));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testWhenAResourceHasNoRegisteredLoader()
    {
        $translator = new Translator('en', new MessageSelector());
        $translator->addResource('array', array('foo' => 'foofoo'), 'en');

        $translator->trans('foo');
    }

    /**
     * @dataProvider getTransTests
     */
    public function testTrans($expected, $id, $translation, $parameters, $locale, $domain)
    {
        $translator = new Translator('en', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array((string) $id => $translation), $locale, $domain);

        $this->assertEquals($expected, $translator->trans($id, $parameters, $domain, $locale));
    }

    /**
     * @dataProvider getFlattenedTransTests
     */
    public function testFlattenedTrans($expected, $messages, $id)
    {
        $translator = new Translator('en', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $messages, 'fr', '');

        $this->assertEquals($expected, $translator->trans($id, array(), '', 'fr'));
    }

    /**
     * @dataProvider getTransChoiceTests
     */
    public function testTransChoice($expected, $id, $translation, $number, $parameters, $locale, $domain)
    {
        $translator = new Translator('en', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array((string) $id => $translation), $locale, $domain);

        $this->assertEquals($expected, $translator->transChoice($id, $number, $parameters, $domain, $locale));
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
        );
    }

    public function getTransTests()
    {
        return array(
            array('Symfony2 est super !', 'Symfony2 is great!', 'Symfony2 est super !', array(), 'fr', ''),
            array('Symfony2 est awesome !', 'Symfony2 is %what%!', 'Symfony2 est %what% !', array('%what%' => 'awesome'), 'fr', ''),
            array('Symfony2 est super !', new String('Symfony2 is great!'), 'Symfony2 est super !', array(), 'fr', ''),
        );
    }

    public function getFlattenedTransTests()
    {
        $messages = array(
            'symfony2' => array(
                'is' => array(
                    'great' => 'Symfony2 est super!'
                )
            ),
            'foo' => array(
                'bar' => array(
                    'baz' => 'Foo Bar Baz'
                ),
                'baz' => 'Foo Baz',
            ),
        );

        return array(
            array('Symfony2 est super!', $messages, 'symfony2.is.great'),
            array('Foo Bar Baz', $messages, 'foo.bar.baz'),
            array('Foo Baz', $messages, 'foo.baz'),
        );
    }

    public function getTransChoiceTests()
    {
        return array(
            array('Il y a 0 pomme', '{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples', '[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes', 0, array('%count%' => 0), 'fr', ''),
            array('Il y a 1 pomme', '{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples', '[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes', 1, array('%count%' => 1), 'fr', ''),
            array('Il y a 10 pommes', '{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples', '[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes', 10, array('%count%' => 10), 'fr', ''),

            array('Il y a 0 pomme', 'There is one apple|There is %count% apples', 'Il y a %count% pomme|Il y a %count% pommes', 0, array('%count%' => 0), 'fr', ''),
            array('Il y a 1 pomme', 'There is one apple|There is %count% apples', 'Il y a %count% pomme|Il y a %count% pommes', 1, array('%count%' => 1), 'fr', ''),
            array('Il y a 10 pommes', 'There is one apple|There is %count% apples', 'Il y a %count% pomme|Il y a %count% pommes', 10, array('%count%' => 10), 'fr', ''),

            array('Il y a 0 pomme', 'one: There is one apple|more: There is %count% apples', 'one: Il y a %count% pomme|more: Il y a %count% pommes', 0, array('%count%' => 0), 'fr', ''),
            array('Il y a 1 pomme', 'one: There is one apple|more: There is %count% apples', 'one: Il y a %count% pomme|more: Il y a %count% pommes', 1, array('%count%' => 1), 'fr', ''),
            array('Il y a 10 pommes', 'one: There is one apple|more: There is %count% apples', 'one: Il y a %count% pomme|more: Il y a %count% pommes', 10, array('%count%' => 10), 'fr', ''),

            array('Il n\'y a aucune pomme', '{0} There is no apple|one: There is one apple|more: There is %count% apples', '{0} Il n\'y a aucune pomme|one: Il y a %count% pomme|more: Il y a %count% pommes', 0, array('%count%' => 0), 'fr', ''),
            array('Il y a 1 pomme', '{0} There is no apple|one: There is one apple|more: There is %count% apples', '{0} Il n\'y a aucune pomme|one: Il y a %count% pomme|more: Il y a %count% pommes', 1, array('%count%' => 1), 'fr', ''),
            array('Il y a 10 pommes', '{0} There is no apple|one: There is one apple|more: There is %count% apples', '{0} Il n\'y a aucune pomme|one: Il y a %count% pomme|more: Il y a %count% pommes', 10, array('%count%' => 10), 'fr', ''),

            array('Il y a 0 pomme', new String('{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples'), '[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes', 0, array('%count%' => 0), 'fr', ''),
        );
    }

    public function testTransChoiceFallback()
    {
        $translator = new Translator('ru', new MessageSelector());
        $translator->setFallbackLocales(array('en'));
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('some_message2' => 'one thing|%count% things'), 'en');

        $this->assertEquals('10 things', $translator->transChoice('some_message2', 10, array('%count%' => 10)));
    }

    public function testTransChoiceFallbackBis()
    {
        $translator = new Translator('ru', new MessageSelector());
        $translator->setFallbackLocales(array('en_US', 'en'));
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('some_message2' => 'one thing|%count% things'), 'en_US');

        $this->assertEquals('10 things', $translator->transChoice('some_message2', 10, array('%count%' => 10)));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testTransChoiceFallbackWithNoTranslation()
    {
        $translator = new Translator('ru', new MessageSelector());
        $translator->setFallbackLocales(array('en'));
        $translator->addLoader('array', new ArrayLoader());

        $this->assertEquals('10 things', $translator->transChoice('some_message2', 10, array('%count%' => 10)));
    }

    /**
     * @dataProvider getTransCascadingTests()
     */
    public function testTransCascading($expected, $id, $domains, $locale)
    {
        $translator = new Translator('en', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $this->addTransCascadingData($translator);

        $this->assertEquals($expected, $translator->trans($id, array(), $domains, $locale));
    }

    /**
     * @dataProvider getTransChoiceCascadingTests()
     */
    public function testTransChoiceCascading($expected, $id, $count, $domains, $locale)
    {
        $translator = new Translator('en', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $this->addTransCascadingData($translator);

        $this->assertEquals($expected, $translator->transChoice($id, $count, array('%count%' => $count), $domains, $locale));
    }


    public function addTransCascadingData(Translator $translator)
    {
        $items = array(
            'generic_domain' => array(
                'element_create' => array(
                    'fr' => 'Nouvel élément',
                ),
                'element_delete' => array(
                    'en' => 'Delete element',
                ),
                'element_count' => array(
                    'en' => '{0} There is no element|{1} There is one element|]1,Inf] There are %count% elements',
                    'fr' => '{0} Pas d\'élément|{1} Il y a un élément|]1,Inf] Il y a %count% éléments',
                ),
            ),
            'task_domain' => array(
                'element_create' => array(
                    'en' => 'New task',
                    'fr' => 'Nouvelle tâche',
                ),
                'element_delete' => array(
                    'en' => 'Delete task',
                ),
                'element_count' => array(
                    'fr' => '{0} Pas de tâche|{1} Il y a une tâche|]1,Inf] Il y a %count% tâches',
                ),
            ),
            'update_task_domain' => array(
                'element_create' => array(
                    'fr' => 'Nouvelle tâche de mise à jour',                        
                ),
                'element_delete' => array(
                    'en' => 'Delete update task'
                ),
                'element_count' => array(
                    'en' => '{0} No update task|{1} There is one update task|]1,Inf] There are %count% update tasks',
                ),
            ),
        );

        foreach ($items as $domain => $data) {
            foreach ($data as $id => $translations) {
                foreach ($translations as $locale => $message) {
                    $translator->addResource('array', array($id => $message), $locale, $domain);
                }
            }
        }
    }

    public function getTransChoiceCascadingTests()
    {
        return array(
            // Classic domain usage
            array('Pas d\'élément', 'element_count', 0, 'generic_domain', 'fr'),
            array('Il y a une tâche', 'element_count', 1, 'task_domain', 'fr'),
            array('There are 10 update tasks', 'element_count', 10, 'update_task_domain', 'en'),
            // Single element array domains
            array('Pas d\'élément', 'element_count', 0, array('generic_domain'), 'fr'),
            array('Il y a une tâche', 'element_count', 1, array('task_domain'), 'fr'),
            array('There are 10 update tasks', 'element_count', 10, array('update_task_domain'), 'en'),
            // Fallback: Cascading domain in the right order
            array('There is no element', 'element_count', 0, array('task_domain', 'generic_domain'), 'en'),
            array('Il y a une tâche', 'element_count', 1, array('update_task_domain', 'task_domain', 'generic_domain'), 'fr'),
            array('There are 10 elements', 'element_count', 10, array('generic_domain', 'update_task_domain', 'task_domain'), 'en'),
        );
    }

    public function getTransCascadingTests()
    {
        return array(
            // Classic domain usage
            array('Nouvel élément', 'element_create', 'generic_domain', 'fr'),
            array('Delete task', 'element_delete', 'task_domain', 'en'),
            array('Nouvelle tâche de mise à jour', 'element_create', 'update_task_domain', 'fr'),
            // Single element array domains
            array('Nouvel élément', 'element_create', array('generic_domain'), 'fr'),
            array('Delete task', 'element_delete', array('task_domain'), 'en'),
            array('Nouvelle tâche de mise à jour', 'element_create', array('update_task_domain'), 'fr'),
            // Fallback: Cascading domain in the right order
            array('Nouvelle tâche', 'element_create', array('task_domain', 'generic_domain'), 'fr'),
            array('Delete update task', 'element_delete', array('update_task_domain', 'generic_domain'), 'en'),
            array('Nouvelle tâche de mise à jour', 'element_create', array('update_task_domain', 'task_domain', 'generic_domain'), 'fr'),
            array('New task', 'element_create', array('update_task_domain', 'task_domain', 'generic_domain'), 'en'),
            array('element_delete', 'element_delete', array('update_task_domain', 'task_domain', 'generic_domain'), 'fr'),
            array('New task', 'element_create', array('generic_domain', 'task_domain', 'update_task_domain'), 'en'),
        );
    }
}

class String
{
    protected $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}
