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
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;
use Symfony\Component\Templating\Loader\Loader;
use Symfony\Component\Templating\Storage\StringStorage;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * @group legacy
 */
class TimedPhpEngineTest extends TestCase
{
    public function testThatRenderLogsTime()
    {
        $container = $this->createMock(Container::class);
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

    private function getTemplateNameParser(): TemplateNameParserInterface
    {
        $templateReference = $this->createMock(TemplateReferenceInterface::class);
        $templateNameParser = $this->createMock(TemplateNameParserInterface::class);
        $templateNameParser->expects($this->any())
            ->method('parse')
            ->willReturn($templateReference);

        return $templateNameParser;
    }

    private function getGlobalVariables(): GlobalVariables
    {
        return $this->createMock(GlobalVariables::class);
    }

    private function getStorage(): StringStorage
    {
        return $this->getMockBuilder(StringStorage::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    /**
     * @param StringStorage $storage
     */
    private function getLoader($storage): Loader
    {
        $loader = $this->getMockForAbstractClass(Loader::class);
        $loader->expects($this->once())
            ->method('load')
            ->willReturn($storage);

        return $loader;
    }

    private function getStopwatchEvent(): StopwatchEvent
    {
        return $this->createMock(StopwatchEvent::class);
    }

    private function getStopwatch(): Stopwatch
    {
        return $this->createMock(Stopwatch::class);
    }
}
