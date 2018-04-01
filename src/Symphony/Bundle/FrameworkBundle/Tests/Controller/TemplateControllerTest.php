<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Controller;

use Symphony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symphony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symphony\Bundle\FrameworkBundle\Tests\TestCase;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TemplateControllerTest extends TestCase
{
    public function testTwig()
    {
        $twig = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $twig->expects($this->exactly(2))->method('render')->willReturn('bar');

        $controller = new TemplateController($twig);

        $this->assertEquals('bar', $controller->templateAction('mytemplate')->getContent());
        $this->assertEquals('bar', $controller('mytemplate')->getContent());
    }

    public function testTemplating()
    {
        $templating = $this->getMockBuilder(EngineInterface::class)->getMock();
        $templating->expects($this->exactly(2))->method('render')->willReturn('bar');

        $controller = new TemplateController(null, $templating);

        $this->assertEquals('bar', $controller->templateAction('mytemplate')->getContent());
        $this->assertEquals('bar', $controller('mytemplate')->getContent());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage You can not use the TemplateController if the Templating Component or the Twig Bundle are not available.
     */
    public function testNoTwigNorTemplating()
    {
        $controller = new TemplateController();

        $controller->templateAction('mytemplate')->getContent();
        $controller('mytemplate')->getContent();
    }
}
