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

class DataCollectorTranslatorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollectMessages()
    {
        $collector = $this->createCollector();
        $collector->setFallbackLocales(array('fr', 'ru'));

        $collector->trans('foo');
        $collector->trans('bar');
        $collector->transChoice('choice', 0);
        $collector->trans('bar_ru');

        $expectedMessages = array();
        $expectedMessages[] = array(
              'id' => 'foo',
              'translation' => 'foo (en)',
              'locale' => 'en',
              'domain' => 'messages',
              'state' => DataCollectorTranslator::MESSAGE_DEFINED,
        );
        $expectedMessages[] = array(
              'id' => 'bar',
              'translation' => 'bar (fr)',
              'locale' => 'fr',
              'domain' => 'messages',
              'state' => DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
        );
        $expectedMessages[] = array(
              'id' => 'choice',
              'translation' => 'choice',
              'locale' => 'en',
              'domain' => 'messages',
              'state' => DataCollectorTranslator::MESSAGE_MISSING,
        );
        $expectedMessages[] = array(
              'id' => 'bar_ru',
              'translation' => 'bar (ru)',
              'locale' => 'ru',
              'domain' => 'messages',
              'state' => DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
        );

        $this->assertEquals($expectedMessages, $collector->getCollectedMessages());
    }

    private function createCollector()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('foo' => 'foo (en)'), 'en');
        $translator->addResource('array', array('bar' => 'bar (fr)'), 'fr');
        $translator->addResource('array', array('bar_ru' => 'bar (ru)'), 'ru');

        $collector = new DataCollectorTranslator($translator);

        return $collector;
    }
}
