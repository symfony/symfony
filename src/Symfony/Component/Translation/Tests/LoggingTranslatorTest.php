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
use Symfony\Component\Translation\LoggingTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageCatalogueProvider\MessageCatalogueProvider;

class LoggingTranslatorTest extends \PHPUnit_Framework_TestCase
{
    public function testTransWithNoTranslationIsLogged()
    {
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $logger->expects($this->exactly(2))
            ->method('warning')
            ->with('Translation not found.')
        ;

        $translator = $this->getTranslator('ar');
        $loggableTranslator = new LoggingTranslator($translator, $logger);
        $loggableTranslator->transChoice('some_message2', 10, array('%count%' => 10));
        $loggableTranslator->trans('bar');
    }

    public function testTransChoiceFallbackIsLogged()
    {
        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $logger->expects($this->once())
            ->method('debug')
            ->with('Translation use fallback catalogue.')
        ;

        $loaders = array('array' => new ArrayLoader());
        $resources = array(
            array('array', array('some_message2' => 'one thing|%count% things'), 'en'),
        );
        $translator = $this->getTranslator('ar', $loaders, $resources, array('en'));
        $loggableTranslator = new LoggingTranslator($translator, $logger);
        $loggableTranslator->transChoice('some_message2', 10, array('%count%' => 10));
    }

    private function getTranslator($locale, $loaders = array(), $resources = array(), $fallbackLocales = array())
    {
        $resourceCatalogue = new MessageCatalogueProvider($loaders, $resources, $fallbackLocales);

        return new Translator($locale, $resourceCatalogue);
    }
}
