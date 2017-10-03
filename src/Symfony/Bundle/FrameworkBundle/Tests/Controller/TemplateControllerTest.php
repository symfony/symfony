<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TemplateControllerTest extends TestCase
{
    public function testTwig()
    {
        $twig = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $twig->expects($this->once())->method('render')->willReturn('bar');

        $controller = new TemplateController($twig);

        $this->assertEquals('bar', $controller->templateAction('mytemplate')->getContent());
    }

    public function testTemplating()
    {
        $templating = $this->getMockBuilder(EngineInterface::class)->getMock();
        $templating->expects($this->once())->method('render')->willReturn('bar');

        $controller = new TemplateController(null, $templating);

        $this->assertEquals('bar', $controller->templateAction('mytemplate')->getContent());
    }

    /**
     * @group legacy
     */
    public function testLegacyTwig()
    {
        $twig = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $twig->expects($this->once())->method('render')->willReturn('bar');

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->will($this->returnValue(false));
        $container->expects($this->at(1))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(2))->method('get')->will($this->returnValue($twig));

        $controller = new TemplateController();
        $controller->setContainer($container);

        $this->assertEquals('bar', $controller->templateAction('mytemplate')->getContent());
    }

    /**
     * @group legacy
     */
    public function testLegacyTemplating()
    {
        $templating = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Templating\EngineInterface')->getMock();
        $templating->expects($this->once())->method('render')->willReturn('bar');

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container->expects($this->at(0))->method('has')->willReturn(true);
        $container->expects($this->at(1))->method('get')->will($this->returnValue($templating));

        $controller = new TemplateController();
        $controller->setContainer($container);

        $this->assertEquals('bar', $controller->templateAction('mytemplate')->getContent());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage You can not use the TemplateController if the Templating Component or the Twig Bundle are not available.
     */
    public function testNoTwigNorTemplating()
    {
        $controller = new TemplateController();

        $controller->templateAction('mytemplate')->getContent();
    }
}
