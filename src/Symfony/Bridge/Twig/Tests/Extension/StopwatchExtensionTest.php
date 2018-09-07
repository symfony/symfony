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
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

class StopwatchExtensionTest extends TestCase
{
    /**
     * @expectedException \Twig\Error\SyntaxError
     */
    public function testFailIfStoppingWrongEvent()
    {
        $this->testTiming('{% stopwatch "foo" %}{% endstopwatch "bar" %}', array());
    }

    /**
     * @dataProvider getTimingTemplates
     */
    public function testTiming($template, $events)
    {
        $twig = new Environment(new ArrayLoader(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new StopwatchExtension($this->getStopwatch($events)));

        try {
            $nodes = $twig->render('template');
        } catch (RuntimeError $e) {
            throw $e->getPrevious();
        }
    }

    public function getTimingTemplates()
    {
        return array(
            array('{% stopwatch "foo" %}something{% endstopwatch %}', 'foo'),
            array('{% stopwatch "foo" %}symfony is fun{% endstopwatch %}{% stopwatch "bar" %}something{% endstopwatch %}', array('foo', 'bar')),
            array('{% set foo = "foo" %}{% stopwatch foo %}something{% endstopwatch %}', 'foo'),
            array('{% set foo = "foo" %}{% stopwatch foo %}something {% set foo = "bar" %}{% endstopwatch %}', 'foo'),
            array('{% stopwatch "foo.bar" %}something{% endstopwatch %}', 'foo.bar'),
            array('{% stopwatch "foo" %}something{% endstopwatch %}{% stopwatch "foo" %}something else{% endstopwatch %}', array('foo', 'foo')),
        );
    }

    protected function getStopwatch($events = array())
    {
        $events = \is_array($events) ? $events : array($events);
        $stopwatch = $this->getMockBuilder('Symfony\Component\Stopwatch\Stopwatch')->getMock();

        $i = -1;
        foreach ($events as $eventName) {
            $stopwatch->expects($this->at(++$i))
                ->method('start')
                ->with($this->equalTo($eventName), 'template')
            ;
            $stopwatch->expects($this->at(++$i))
                ->method('stop')
                ->with($this->equalTo($eventName))
            ;
        }

        return $stopwatch;
    }
}
