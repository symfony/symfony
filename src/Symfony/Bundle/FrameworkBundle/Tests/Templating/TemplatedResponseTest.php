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

use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Bundle\FrameworkBundle\Templating\TemplatedResponse;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class TemplatedResponseTest extends TestCase
{
    public function testResponse()
    {
        $templating = $this->getMockBuilder(EngineInterface::class)->getMock();

        $templating->expects($this->once())
            ->method('renderResponse')
            ->with('dummy_template.html.php', array('var' => 'dummy'))
            ->will($this->returnValue(new Response()));

        $templateResponse = new TemplatedResponse('dummy_template.html.php', array('var' => 'dummy'));

        $this->assertInstanceOf(Response::class, $templateResponse->getResponse($templating));
    }

    public function testSameResponse()
    {
        $templating = $this->getMockBuilder(EngineInterface::class)->getMock();

        $response = new Response();
        $templating->expects($this->once())
            ->method('renderResponse')
            ->with('dummy_template.html.php', array('var' => 'dummy'))
            ->will($this->returnValue($response));

        $templateResponse = new TemplatedResponse('dummy_template.html.php', array('var' => 'dummy'), $response);

        $this->assertSame($response, $templateResponse->getResponse($templating));
    }
}
