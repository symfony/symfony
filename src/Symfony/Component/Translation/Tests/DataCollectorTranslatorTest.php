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
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageCatalogueProvider\ResourceMessageCatalogueProvider;

class DataCollectorTranslatorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollectMessages()
    {
        $collector = $this->createCollector();
        $collector->trans('foo');
        $collector->trans('bar');
        $collector->transChoice('choice', 0);
        $collector->trans('bar_ru');
        $collector->trans('bar_ru', array('foo' => 'bar'));

        $expectedMessages = array();
        $expectedMessages[] = array(
              'id' => 'foo',
              'translation' => 'foo (en)',
              'locale' => 'en',
              'domain' => 'messages',
              'state' => DataCollectorTranslator::MESSAGE_DEFINED,
              'parameters' => array(),
              'transChoiceNumber' => null,
        );
        $expectedMessages[] = array(
              'id' => 'bar',
              'translation' => 'bar (fr)',
              'locale' => 'fr',
              'domain' => 'messages',
              'state' => DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
              'parameters' => array(),
              'transChoiceNumber' => null,
        );
        $expectedMessages[] = array(
              'id' => 'choice',
              'translation' => 'choice',
              'locale' => 'en',
              'domain' => 'messages',
              'state' => DataCollectorTranslator::MESSAGE_MISSING,
              'parameters' => array(),
              'transChoiceNumber' => 0,
        );
        $expectedMessages[] = array(
              'id' => 'bar_ru',
              'translation' => 'bar (ru)',
              'locale' => 'ru',
              'domain' => 'messages',
              'state' => DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
              'parameters' => array(),
              'transChoiceNumber' => null,
        );
        $expectedMessages[] = array(
              'id' => 'bar_ru',
              'translation' => 'bar (ru)',
              'locale' => 'ru',
              'domain' => 'messages',
              'state' => DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
              'parameters' => array('foo' => 'bar'),
              'transChoiceNumber' => null,
        );

        $this->assertEquals($expectedMessages, $collector->getCollectedMessages());
    }

    private function createCollector()
    {
        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('foo' => 'foo (en)'), 'en'),
            array('array', array('bar' => 'bar (fr)'), 'fr'),
            array('array', array('bar_ru' => 'bar (ru)'), 'ru'),
        );
        $translator = $this->getTranslator('en', $loaders, $resources, array('fr', 'ru'));

        $collector = new DataCollectorTranslator($translator);

        return $collector;
    }

    private function getTranslator($locale, $loaders = array(), $resources = array(), $fallbackLocales = array())
    {
        $resourceCatalogue = new ResourceMessageCatalogueProvider($loaders, $resources, $fallbackLocales);

        return new Translator($locale, $resourceCatalogue);
    }
}
