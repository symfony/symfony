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
        $resolver = $this->getMock('Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver', array(), array(), '', false);
        $resolver->expects($this->any())
                 ->method('render')
                 ->will($this->returnValue('WDT'));
        ;
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $listener = new WebDebugToolbarListener($resolver);
        $m = new \ReflectionMethod($listener, 'injectToolbar');
        $m->setAccessible(true);

        $response = new Response($content);

        $m->invoke($listener, $request, $response);
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
