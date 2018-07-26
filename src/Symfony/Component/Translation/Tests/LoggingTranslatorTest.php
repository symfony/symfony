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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\LoggingTranslator;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorFallbackInterface;

class LoggingTranslatorTest extends TestCase
{
    public function testTransWithNoTranslationIsLogged()
    {
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $logger->expects($this->exactly(2))
            ->method('warning')
            ->with('Translation not found.')
        ;

        $translator = new Translator('ar');
        $loggableTranslator = new LoggingTranslator($translator, $logger);
        $loggableTranslator->transChoice('some_message2', 10, array('%count%' => 10));
        $loggableTranslator->trans('bar');
    }

    public function testTransChoiceFallbackIsLogged()
    {
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $logger->expects($this->once())
            ->method('debug')
            ->with('Translation use fallback catalogue.')
        ;

        $translator = new Translator('ar');
        $translator->setFallbackLocales(array('en'));
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array('some_message2' => 'one thing|%count% things'), 'en');
        $loggableTranslator = new LoggingTranslator($translator, $logger);
        $loggableTranslator->transChoice('some_message2', 10, array('%count%' => 10));
    }

    public function testFallbackLocalesReturned()
    {
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();

        $internalTranslator = new Translator('en');
        $internalTranslator->setFallbackLocales(array('bg'));

        $translator = new LoggingTranslator($internalTranslator, $logger);

        $this->assertInstanceOf(TranslatorFallbackInterface::class, $translator);
        $fallbackLocales = $translator->getFallbackLocales();
        $this->assertCount(1, $fallbackLocales);
        $this->assertEquals('bg', $fallbackLocales[0]);
    }

    public function testWrappedTranslatorWithFallbackLocalesWithoutImplementingFallbackInterface()
    {
        set_error_handler(
            function () {
                return false;
            }
        );
        $e = error_reporting(0);
        trigger_error('', E_USER_NOTICE);

        $internalTranslator = new DummyTranslator('en', array('bg'));

        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $translator = new LoggingTranslator($internalTranslator, $logger);

        $fallbackLocales = $translator->getFallbackLocales();
        $this->assertCount(1, $fallbackLocales);
        $this->assertEquals('bg', $fallbackLocales[0]);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $expected = array(
            'type' => E_USER_DEPRECATED,
            'message' => sprintf('Having `getFallbackLocales` in %s without implementing %s is deprecated', get_class($internalTranslator), TranslatorFallbackInterface::class),
        );

        $this->assertSame($expected, $lastError);
    }
}
