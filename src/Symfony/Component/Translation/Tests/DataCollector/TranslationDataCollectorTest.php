<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\DataCollector\TranslationDataCollector;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class TranslationDataCollectorTest extends TestCase
{
    public function testCollectEmptyMessages()
    {
        $dataCollector = new TranslationDataCollector(new DataCollectorTranslator($this->createMock(Translator::class)));
        $dataCollector->lateCollect();

        $this->assertEquals(0, $dataCollector->getCountMissings());
        $this->assertEquals(0, $dataCollector->getCountFallbacks());
        $this->assertEquals(0, $dataCollector->getCountDefines());
        $this->assertEquals([], $dataCollector->getMessages()->getValue());
    }

    public function testCollect()
    {
        $expectedMessages = [
            [
                'id' => 'foo',
                'translation' => 'foo (en)',
                'locale' => 'en',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_DEFINED,
                'count' => 1,
                'parameters' => [],
                'transChoiceNumber' => null,
                'fallbackLocale' => null,
            ],
            [
                'id' => 'bar',
                'translation' => 'bar (fr)',
                'locale' => 'en',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
                'count' => 1,
                'parameters' => [],
                'transChoiceNumber' => null,
                'fallbackLocale' => 'fr',
            ],
            [
                'id' => 'choice',
                'translation' => 'choice',
                'locale' => 'en',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_MISSING,
                'count' => 3,
                'parameters' => [
                    ['%count%' => 3],
                    ['%count%' => 3],
                    ['%count%' => 4, '%foo%' => 'bar'],
                ],
                'transChoiceNumber' => 3,
                'fallbackLocale' => null,
            ],
        ];

        $translator = new Translator('en');
        $translator->setFallbackLocales(['fr']);
        $translator->addLoader('memory', new ArrayLoader());
        $translator->addResource('memory', ['foo' => 'foo (en)'], 'en');
        $translator->addResource('memory', ['bar' => 'bar (fr)'], 'fr');
        $dataCollectorTranslator = new DataCollectorTranslator($translator);
        $dataCollectorTranslator->trans('foo');
        $dataCollectorTranslator->trans('bar');
        $dataCollectorTranslator->trans('choice', ['%count%' => 3]);
        $dataCollectorTranslator->trans('choice', ['%count%' => 3]);
        $dataCollectorTranslator->trans('choice', ['%count%' => 4, '%foo%' => 'bar']);
        $dataCollector = new TranslationDataCollector($dataCollectorTranslator);
        $dataCollector->lateCollect();

        $this->assertEquals(1, $dataCollector->getCountMissings());
        $this->assertEquals(1, $dataCollector->getCountFallbacks());
        $this->assertEquals(1, $dataCollector->getCountDefines());

        $this->assertEquals($expectedMessages, array_values($dataCollector->getMessages()->getValue(true)));
    }

    public function testCollectAndReset()
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['en']);
        $translator->addGlobalParameter('welcome', 'Welcome {name}!');
        $dataCollector = new TranslationDataCollector(new DataCollectorTranslator($translator));
        $dataCollector->collect($this->createMock(Request::class), $this->createMock(Response::class));

        $this->assertSame('fr', $dataCollector->getLocale());
        $this->assertSame(['en'], $dataCollector->getFallbackLocales());
        $this->assertSame(['welcome' => 'Welcome {name}!'], $dataCollector->getGlobalParameters());

        $dataCollector->reset();

        $this->assertNull($dataCollector->getLocale());
        $this->assertSame([], $dataCollector->getFallbackLocales());
        $this->assertSame([], $dataCollector->getGlobalParameters());
    }
}
