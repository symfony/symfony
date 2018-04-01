<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Templating;

use Symphony\Bundle\FrameworkBundle\Templating\TimedPhpEngine;
use Symphony\Component\DependencyInjection\Container;
use Symphony\Bundle\FrameworkBundle\Templating\GlobalVariables;
use Symphony\Bundle\FrameworkBundle\Tests\TestCase;

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
            ->will($this->returnValue($stopwatchEvent));

        $stopwatchEvent->expects($this->once())->method('stop');

        $engine = new TimedPhpEngine($templateNameParser, $container, $loader, $stopwatch, $globalVariables);
        $engine->render('index.php');
    }

    /**
     * @return Container
     */
    private function getContainer()
    {
        return $this->getMockBuilder('Symphony\Component\DependencyInjection\Container')->getMock();
    }

    /**
     * @return \Symphony\Component\Templating\TemplateNameParserInterface
     */
    private function getTemplateNameParser()
    {
        $templateReference = $this->getMockBuilder('Symphony\Component\Templating\TemplateReferenceInterface')->getMock();
        $templateNameParser = $this->getMockBuilder('Symphony\Component\Templating\TemplateNameParserInterface')->getMock();
        $templateNameParser->expects($this->any())
            ->method('parse')
            ->will($this->returnValue($templateReference));

        return $templateNameParser;
    }

    /**
     * @return GlobalVariables
     */
    private function getGlobalVariables()
    {
        return $this->getMockBuilder('Symphony\Bundle\FrameworkBundle\Templating\GlobalVariables')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \Symphony\Component\Templating\Storage\StringStorage
     */
    private function getStorage()
    {
        return $this->getMockBuilder('Symphony\Component\Templating\Storage\StringStorage')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    /**
     * @param \Symphony\Component\Templating\Storage\StringStorage $storage
     *
     * @return \Symphony\Component\Templating\Loader\Loader
     */
    private function getLoader($storage)
    {
        $loader = $this->getMockForAbstractClass('Symphony\Component\Templating\Loader\Loader');
        $loader->expects($this->once())
            ->method('load')
            ->will($this->returnValue($storage));

        return $loader;
    }

    /**
     * @return \Symphony\Component\Stopwatch\StopwatchEvent
     */
    private function getStopwatchEvent()
    {
        return $this->getMockBuilder('Symphony\Component\Stopwatch\StopwatchEvent')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return \Symphony\Component\Stopwatch\Stopwatch
     */
    private function getStopwatch()
    {
        return $this->getMockBuilder('Symphony\Component\Stopwatch\Stopwatch')->getMock();
    }
}
