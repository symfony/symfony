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
use Symfony\Component\Translation\Formatter\IntlMessageFormatter;

class DataCollectorTranslatorTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpKernel\DataCollector\DataCollector')) {
            $this->markTestSkipped('The "DataCollector" is not available');
        }
    }

    /**
     * @group legacy
     */
    public function testCollectLegacyMessages()
    {
        $collector = $this->createCollector();
        $collector->setFallbackLocales(array('fr', 'ru'));
        $collector->transChoice('choice', 0);

        $expectedMessages = array(
            array(
                'id' => 'choice',
                'translation' => 'choice',
                'locale' => 'en',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_MISSING,
                'parameters' => array(),
                'transChoiceNumber' => 0,
            ),
        );

        $this->assertEquals($expectedMessages, $collector->getCollectedMessages());
    }

    public function testCollectMessages()
    {
        $resources = array(
            array('array', array('foo' => 'foo (en)'), 'en'),
            array('array', array('bar' => 'bar (fr)'), 'fr'),
            array('array', array('bar_ru' => '{foo} (ru)'), 'ru'),
        );

        $collector = $this->createCollector($resources);
        $collector->setFallbackLocales(array('fr', 'ru'));

        $collector->trans('foo');
        $collector->trans('bar');
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

    private function createCollector($resources = array())
    {
        $translator = new Translator('en', new IntlMessageFormatter());
        $translator->addLoader('array', new ArrayLoader());
        foreach ($resources as $resource) {
            $translator->addResource($resource[0], $resource[1], $resource[2], isset($resource[3]) ? $resource[3] : null);
        }

        $collector = new DataCollectorTranslator($translator);

        return $collector;
    }
}
