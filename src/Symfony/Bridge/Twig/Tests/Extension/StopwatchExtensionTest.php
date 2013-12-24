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

use Symfony\Bridge\Twig\Extension\StopwatchExtension;

class StopwatchExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Twig_Error_Syntax
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
        $twig = new \Twig_Environment(new \Twig_Loader_String(), array('debug' => true, 'cache' => false, 'autoescape' => true, 'optimizations' => 0));
        $twig->addExtension(new StopwatchExtension($this->getStopwatch($events)));

        try {
            $nodes = $twig->render($template);
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getTimingTemplates()
    {
        return array(
            array('{% stopwatch "foo" %}something{% endstopwatch %}', 'foo'),
            array('{% stopwatch "foo" %}symfony2 is fun{% endstopwatch %}{% stopwatch "bar" %}something{% endstopwatch %}', array('foo', 'bar')),
            array('{% set foo = "foo" %}{% stopwatch foo %}something{% endstopwatch %}', 'foo'),
            array('{% set foo = "foo" %}{% stopwatch foo %}something {% set foo = "bar" %}{% endstopwatch %}', 'foo'),
            array('{% stopwatch "foo.bar" %}something{% endstopwatch %}', 'foo.bar'),
            array('{% stopwatch "foo" %}something{% endstopwatch %}{% stopwatch "foo" %}something else{% endstopwatch %}', array('foo', 'foo')),
        );
    }

    protected function getStopwatch($events = array())
    {
        $events = is_array($events) ? $events : array($events);
        $stopwatch = $this->getMock('Symfony\Component\Stopwatch\Stopwatch');

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
