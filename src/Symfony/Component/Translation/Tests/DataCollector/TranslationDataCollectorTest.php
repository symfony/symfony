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

use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\DataCollector\TranslationDataCollector;

class TranslationDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpKernel\DataCollector\DataCollector')) {
            $this->markTestSkipped('The "DataCollector" is not available');
        }
    }

    public function testCollectEmptyMessages()
    {
        $translator = $this->getTranslator();
        $translator->expects($this->any())->method('getCollectedMessages')->will($this->returnValue(array()));

        $dataCollector = new TranslationDataCollector($translator);
        $dataCollector->lateCollect();

        $this->assertEquals(0, $dataCollector->getCountMissings());
        $this->assertEquals(0, $dataCollector->getCountFallbacks());
        $this->assertEquals(0, $dataCollector->getCountDefines());
        $this->assertEquals(array(), $dataCollector->getMessages());
    }

    public function testCollect()
    {
        $collectedMessages = array(
            array(
                  'id' => 'foo',
                  'translation' => 'foo (en)',
                  'locale' => 'en',
                  'domain' => 'messages',
                  'state' => DataCollectorTranslator::MESSAGE_DEFINED,
            ),
            array(
                  'id' => 'bar',
                  'translation' => 'bar (fr)',
                  'locale' => 'fr',
                  'domain' => 'messages',
                  'state' => DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
            ),
            array(
                  'id' => 'choice',
                  'translation' => 'choice',
                  'locale' => 'en',
                  'domain' => 'messages',
                  'state' => DataCollectorTranslator::MESSAGE_MISSING,
            ),
            array(
                  'id' => 'choice',
                  'translation' => 'choice',
                  'locale' => 'en',
                  'domain' => 'messages',
                  'state' => DataCollectorTranslator::MESSAGE_MISSING,
            ),
        );
        $expectedMessages = array(
            array(
                  'id' => 'foo',
                  'translation' => 'foo (en)',
                  'locale' => 'en',
                  'domain' => 'messages',
                  'state' => DataCollectorTranslator::MESSAGE_DEFINED,
                  'count' => 1,
            ),
            array(
                  'id' => 'bar',
                  'translation' => 'bar (fr)',
                  'locale' => 'fr',
                  'domain' => 'messages',
                  'state' => DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
                  'count' => 1,
            ),
            array(
                  'id' => 'choice',
                  'translation' => 'choice',
                  'locale' => 'en',
                  'domain' => 'messages',
                  'state' => DataCollectorTranslator::MESSAGE_MISSING,
                  'count' => 2,
            ),
        );

        $translator = $this->getTranslator();
        $translator->expects($this->any())->method('getCollectedMessages')->will($this->returnValue($collectedMessages));

        $dataCollector = new TranslationDataCollector($translator);
        $dataCollector->lateCollect();

        $this->assertEquals(1, $dataCollector->getCountMissings());
        $this->assertEquals(1, $dataCollector->getCountFallbacks());
        $this->assertEquals(1, $dataCollector->getCountDefines());
        $this->assertEquals($expectedMessages, array_values($dataCollector->getMessages()));
    }

    private function getTranslator()
    {
        $translator = $this
            ->getMockBuilder('Symfony\Component\Translation\DataCollectorTranslator')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        return $translator;
    }
}
