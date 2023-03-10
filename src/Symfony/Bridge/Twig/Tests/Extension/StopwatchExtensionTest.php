<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\StopwatchExtension;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

class StopwatchExtensionTest extends TestCase
{
    public function testFailIfStoppingWrongEvent()
    {
        $this->expectException(\Twig\Error\SyntaxError::class);
        $this->testTiming('{% stopwatch "foo" %}{% endstopwatch "bar" %}', []);
    }

    /**
     * @dataProvider getTimingTemplates
     */
    public function testTiming($template, $events)
    {
        $twig = new Environment(new ArrayLoader(['template' => $template]), ['debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0]);
        $twig->addExtension(new StopwatchExtension($this->getStopwatch($events)));

        try {
            $twig->render('template');
        } catch (RuntimeError $e) {
            throw $e->getPrevious();
        }
    }

    public static function getTimingTemplates()
    {
        return [
            ['{% stopwatch "foo" %}something{% endstopwatch %}', 'foo'],
            ['{% stopwatch "foo" %}symfony is fun{% endstopwatch %}{% stopwatch "bar" %}something{% endstopwatch %}', ['foo', 'bar']],
            ['{% set foo = "foo" %}{% stopwatch foo %}something{% endstopwatch %}', 'foo'],
            ['{% set foo = "foo" %}{% stopwatch foo %}something {% set foo = "bar" %}{% endstopwatch %}', 'foo'],
            ['{% stopwatch "foo.bar" %}something{% endstopwatch %}', 'foo.bar'],
            ['{% stopwatch "foo" %}something{% endstopwatch %}{% stopwatch "foo" %}something else{% endstopwatch %}', ['foo', 'foo']],
        ];
    }

    protected function getStopwatch($events = [])
    {
        $events = \is_array($events) ? $events : [$events];
        $stopwatch = $this->createMock(Stopwatch::class);

        $expectedCalls = 0;
        $expectedStartCalls = [];
        $expectedStopCalls = [];
        foreach ($events as $eventName) {
            ++$expectedCalls;
            $expectedStartCalls[] = [$this->equalTo($eventName), 'template'];
            $expectedStopCalls[] = [$this->equalTo($eventName)];
        }

        $stopwatch
            ->expects($this->exactly($expectedCalls))
            ->method('start')
            ->willReturnCallback(function (string $name, string $category) use (&$expectedStartCalls) {
                [$expectedName, $expectedCategory] = array_shift($expectedStartCalls);

                $expectedName->evaluate($name);
                $this->assertSame($expectedCategory, $category);

                return $this->createMock(StopwatchEvent::class);
            })
        ;

        $stopwatch
            ->expects($this->exactly($expectedCalls))
            ->method('stop')
            ->willReturnCallback(function (string $name) use (&$expectedStopCalls) {
                [$expectedName] = array_shift($expectedStopCalls);
                $expectedName->evaluate($name);

                return $this->createMock(StopwatchEvent::class);
            })
        ;

        return $stopwatch;
    }
}
