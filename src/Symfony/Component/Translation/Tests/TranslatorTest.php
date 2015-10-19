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
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueProvider\MessageCatalogueProvider;

class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorInvalidLocale($locale)
    {
        $translator = $this->getTranslator($locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testConstructorValidLocale($locale)
    {
        $translator = $this->getTranslator($locale);

        $this->assertEquals($locale, $translator->getLocale());
    }

    public function testConstructorWithoutLocale()
    {
        $translator = $this->getTranslator(null);

        $this->assertNull($translator->getLocale());
    }

    public function testSetGetLocale()
    {
        $translator = $this->getTranslator('en');

        $this->assertEquals('en', $translator->getLocale());

        $translator->setLocale('fr');
        $this->assertEquals('fr', $translator->getLocale());
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \InvalidArgumentException
     */
    public function testSetInvalidLocale($locale)
    {
        $translator = $this->getTranslator('fr');
        $translator->setLocale($locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testSetValidLocale($locale)
    {
        $translator = $this->getTranslator($locale);
        $translator->setLocale($locale);

        $this->assertEquals($locale, $translator->getLocale());
    }

    public function testGetCatalogue()
    {
        $translator = $this->getTranslator('en');

        $this->assertEquals(new MessageCatalogue('en'), $translator->getCatalogue());

        $translator->setLocale('fr');
        $this->assertEquals(new MessageCatalogue('fr'), $translator->getCatalogue('fr'));
    }

    public function testGetCatalogueReturnsConsolidatedCatalogue()
    {
        /*
         * This will be useful once we refactor so that different domains will be loaded lazily (on-demand).
         * In that case, getCatalogue() will probably have to load all missing domains in order to return
         * one complete catalogue.
         */

        $locale = 'whatever';
        $loaders = array(
            'loader-a' => new ArrayLoader(),
            'loader-b' => new ArrayLoader(),
        );
        $resources = array(
            array('loader-a', array('foo' => 'foofoo'), $locale, 'domain-a'),
            array('loader-b', array('bar' => 'foobar'), $locale, 'domain-b'),
        );

        $translator = $this->getTranslator($locale, $loaders, $resources);

        /*
         * Test that we get a single catalogue comprising messages
         * from different loaders and different domains
         */
        $catalogue = $translator->getCatalogue($locale);
        $this->assertTrue($catalogue->defines('foo', 'domain-a'));
        $this->assertTrue($catalogue->defines('bar', 'domain-b'));
    }

    /**
     * @dataProvider getTransTests
     */
    public function testTrans($expected, $id, $translation, $parameters, $locale, $domain)
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(array('array', array((string) $id => $translation), $locale, $domain));
        $translator = $this->getTranslator('en', $loaders, $resources);

        $this->assertEquals($expected, $translator->trans($id, $parameters, $domain, $locale));
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \InvalidArgumentException
     */
    public function testTransInvalidLocale($locale)
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(array('array', array('foo' => 'foofoo'), 'en'));
        $translator = $this->getTranslator('en', $loaders, $resources);

        $translator->trans('foo', array(), '', $locale);
    }

    /**
     * @dataProvider      getValidLocalesTests
     */
    public function testTransValidLocale($locale)
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(array('array', array('test' => 'OK'), $locale));
        $translator = $this->getTranslator($locale, $loaders, $resources);

        $this->assertEquals('OK', $translator->trans('test'));
        $this->assertEquals('OK', $translator->trans('test', array(), null, $locale));
    }

    /**
     * @dataProvider getFlattenedTransTests
     */
    public function testFlattenedTrans($expected, $messages, $id)
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(array('array', $messages, 'fr', ''));
        $translator = $this->getTranslator('en', $loaders, $resources);

        $this->assertEquals($expected, $translator->trans($id, array(), '', 'fr'));
    }

    /**
     * @dataProvider getTransChoiceTests
     */
    public function testTransChoice($expected, $id, $translation, $number, $parameters, $locale, $domain)
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(array('array', array((string) $id => $translation), $locale, $domain));
        $translator = $this->getTranslator('en', $loaders, $resources);

        $this->assertEquals($expected, $translator->transChoice($id, $number, $parameters, $domain, $locale));
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \InvalidArgumentException
     */
    public function testTransChoiceInvalidLocale($locale)
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(array('array', array('foo' => 'foofoo'), 'en'));
        $translator = $this->getTranslator('en', $loaders, $resources);

        $translator->transChoice('foo', 1, array(), '', $locale);
    }

    /**
     * @dataProvider      getValidLocalesTests
     */
    public function testTransChoiceValidLocale($locale)
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(array('array', array('foo' => 'foofoo'), 'en'));
        $translator = $this->getTranslator('en', $loaders, $resources);

        $translator->transChoice('foo', 1, array(), '', $locale);
        // no assertion. this method just asserts that no exception is thrown
    }

    public function getTransTests()
    {
        return array(
            array('Symfony est super !', 'Symfony is great!', 'Symfony est super !', array(), 'fr', ''),
            array('Symfony est awesome !', 'Symfony is %what%!', 'Symfony est %what% !', array('%what%' => 'awesome'), 'fr', ''),
            array('Symfony est super !', new StringClass('Symfony is great!'), 'Symfony est super !', array(), 'fr', ''),
        );
    }

    public function getFlattenedTransTests()
    {
        $messages = array(
            'symfony' => array(
                'is' => array(
                    'great' => 'Symfony est super!',
                ),
            ),
            'foo' => array(
                'bar' => array(
                    'baz' => 'Foo Bar Baz',
                ),
                'baz' => 'Foo Baz',
            ),
        );

        return array(
            array('Symfony est super!', $messages, 'symfony.is.great'),
            array('Foo Bar Baz', $messages, 'foo.bar.baz'),
            array('Foo Baz', $messages, 'foo.baz'),
        );
    }

    public function getTransChoiceTests()
    {
        return array(
            array('Il y a 0 pomme', '{0} There are no appless|{1} There is one apple|]1,Inf] There is %count% apples', '[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes', 0, array('%count%' => 0), 'fr', ''),
            array('Il y a 1 pomme', '{0} There are no appless|{1} There is one apple|]1,Inf] There is %count% apples', '[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes', 1, array('%count%' => 1), 'fr', ''),
            array('Il y a 10 pommes', '{0} There are no appless|{1} There is one apple|]1,Inf] There is %count% apples', '[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes', 10, array('%count%' => 10), 'fr', ''),

            array('Il y a 0 pomme', 'There is one apple|There is %count% apples', 'Il y a %count% pomme|Il y a %count% pommes', 0, array('%count%' => 0), 'fr', ''),
            array('Il y a 1 pomme', 'There is one apple|There is %count% apples', 'Il y a %count% pomme|Il y a %count% pommes', 1, array('%count%' => 1), 'fr', ''),
            array('Il y a 10 pommes', 'There is one apple|There is %count% apples', 'Il y a %count% pomme|Il y a %count% pommes', 10, array('%count%' => 10), 'fr', ''),

            array('Il y a 0 pomme', 'one: There is one apple|more: There is %count% apples', 'one: Il y a %count% pomme|more: Il y a %count% pommes', 0, array('%count%' => 0), 'fr', ''),
            array('Il y a 1 pomme', 'one: There is one apple|more: There is %count% apples', 'one: Il y a %count% pomme|more: Il y a %count% pommes', 1, array('%count%' => 1), 'fr', ''),
            array('Il y a 10 pommes', 'one: There is one apple|more: There is %count% apples', 'one: Il y a %count% pomme|more: Il y a %count% pommes', 10, array('%count%' => 10), 'fr', ''),

            array('Il n\'y a aucune pomme', '{0} There are no apples|one: There is one apple|more: There is %count% apples', '{0} Il n\'y a aucune pomme|one: Il y a %count% pomme|more: Il y a %count% pommes', 0, array('%count%' => 0), 'fr', ''),
            array('Il y a 1 pomme', '{0} There are no apples|one: There is one apple|more: There is %count% apples', '{0} Il n\'y a aucune pomme|one: Il y a %count% pomme|more: Il y a %count% pommes', 1, array('%count%' => 1), 'fr', ''),
            array('Il y a 10 pommes', '{0} There are no apples|one: There is one apple|more: There is %count% apples', '{0} Il n\'y a aucune pomme|one: Il y a %count% pomme|more: Il y a %count% pommes', 10, array('%count%' => 10), 'fr', ''),

            array('Il y a 0 pomme', new StringClass('{0} There are no appless|{1} There is one apple|]1,Inf] There is %count% apples'), '[0,1] Il y a %count% pomme|]1,Inf] Il y a %count% pommes', 0, array('%count%' => 0), 'fr', ''),
        );
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

    public function testTransChoiceFallback()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(array('array', array('some_message2' => 'one thing|%count% things'), 'en'));
        $translator = $this->getTranslator('ru', $loaders, $resources, array('en'));

        $this->assertEquals('10 things', $translator->transChoice('some_message2', 10, array('%count%' => 10)));
    }

    public function testTransChoiceFallbackBis()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(array('array', array('some_message2' => 'one thing|%count% things'), 'en_US'));
        $translator = $this->getTranslator('ru', $loaders, $resources, array('en_US', 'en'));

        $this->assertEquals('10 things', $translator->transChoice('some_message2', 10, array('%count%' => 10)));
    }

    public function testTransChoiceFallbackWithNoTranslation()
    {
        $loaders = array('array' => new ArrayLoader());
        $translator = $this->getTranslator('ru', $loaders, array(), array('en'));

        // consistent behavior with Translator::trans(), which returns the string
        // unchanged if it can't be found
        $this->assertEquals('some_message2', $translator->transChoice('some_message2', 10, array('%count%' => 10)));
    }

    protected function getTranslator($locale, $loaders = array(), $resources = array(), $fallbackLocales = array())
    {
        $resourceCatalogue = new MessageCatalogueProvider($loaders, $resources, $fallbackLocales);

        return new Translator($locale, $resourceCatalogue);
    }
}

class StringClass
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
