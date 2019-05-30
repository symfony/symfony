<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating;

use Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables;
use Symfony\Bundle\FrameworkBundle\Templating\TimedPhpEngine;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * @group legacy
 */
class TimedPhpEngineTest extends TestCase
{
    public function testThatRenderLogsTime()
    {
        $container = $this->getContainer();
        $templateNameParser = $this->getTemplateNameParser();
        $globalVariables = $this->getGlobalVariables();
        $loader = $this->getLoader($this->getStorage());

        $stopwatch = $this->getStopwatch();
        $stopwatchEvent = $this->getStopwatchEvent();

        $stopwatch->expects($this->once())
            ->method('start')
            ->with('template.php (index.php)', 'template')
            ->willReturn($stopwatchEvent);

        $stopwatchEvent->expects($this->once())->method('stop');

        $engine = new TimedPhpEngine($templateNameParser, $container, $loader, $stopwatch, $globalVariables);
        $engine->render('index.php');
    }

    /**
     * @return Container
     */
    private function getContainer()
    {
        return $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')->getMock();
    }

    /**
     * @return \Symfony\Component\Templating\TemplateNameParserInterface
     */
    private function getTemplateNameParser()
    {
        $templateReference = $this->getMockBuilder('Symfony\Component\Templating\TemplateReferenceInterface')->getMock();
        $templateNameParser = $this->getMockBuilder('Symfony\Component\Templating\TemplateNameParserInterface')->getMock();
        $templateNameParser->expects($this->any())
            ->method('parse')
            ->willReturn($templateReference);

        return $templateNameParser;
    }

    /**
     * @return GlobalVariables
     */
    private function getGlobalVariables()
    {
        return $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \Symfony\Component\Templating\Storage\StringStorage
     */
    private function getStorage()
    {
        return $this->getMockBuilder('Symfony\Component\Templating\Storage\StringStorage')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    /**
     * @param \Symfony\Component\Templating\Storage\StringStorage $storage
     *
     * @return \Symfony\Component\Templating\Loader\Loader
     */
    private function getLoader($storage)
    {
        $loader = $this->getMockForAbstractClass('Symfony\Component\Templating\Loader\Loader');
        $loader->expects($this->once())
            ->method('load')
            ->willReturn($storage);

        return $loader;
    }

    /**
     * @return \Symfony\Component\Stopwatch\StopwatchEvent
     */
    private function getStopwatchEvent()
    {
        return $this->getMockBuilder('Symfony\Component\Stopwatch\StopwatchEvent')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \Symfony\Component\Stopwatch\Stopwatch
     */
    private function getStopwatch()
    {
        return $this->getMockBuilder('Symfony\Component\Stopwatch\Stopwatch')->getMock();
    }
}
