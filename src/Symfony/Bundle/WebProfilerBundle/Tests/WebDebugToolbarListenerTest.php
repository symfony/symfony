<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests;

use Symfony\Bundle\WebProfilerBundle\WebDebugToolbarListener;
use Symfony\Component\HttpFoundation\Response;

class WebDebugToolbarListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getInjectToolbarTests
     */
    public function testInjectToolbar($content, $expected)
    {
        $kernel = $this->getMock('Symfony\Bundle\FrameworkBundle\HttpKernel', array(), array(), '', false);
        $templating = $this->getMock('Symfony\Bundle\TwigBundle\TwigEngine', array(), array(), '', false);
        $templating->expects($this->any())
                 ->method('render')
                 ->will($this->returnValue('WDT'));
        ;
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $listener = new WebDebugToolbarListener($kernel, $templating);
        $m = new \ReflectionMethod($listener, 'injectToolbar');
        $m->setAccessible(true);

        $response = new Response($content);

        $m->invoke($listener, $response);
        $this->assertEquals($expected, $response->getContent());
    }

    public function getInjectToolbarTests()
    {
        return array(
            array('<html><head></head><body></body></html>', "<html><head></head><body>\nWDT\n</body></html>"),
            array('<html>
            <head></head>
            <body>
            <textarea><html><head></head><body></body></html></textarea>
            </body>
            </html>', "<html>
            <head></head>
            <body>
            <textarea><html><head></head><body></body></html></textarea>
            
WDT
</body>
            </html>"),
        );
    }
}
