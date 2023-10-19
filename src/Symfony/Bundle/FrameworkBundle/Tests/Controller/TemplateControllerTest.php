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
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TemplateControllerTest extends TestCase
{
    public function testTwig()
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->exactly(2))->method('render')->willReturn('bar');

        $controller = new TemplateController($twig);

        $this->assertEquals('bar', $controller->templateAction('mytemplate')->getContent());
        $this->assertEquals('bar', $controller('mytemplate')->getContent());
    }

    public function testNoTwig()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You cannot use the TemplateController if the Twig Bundle is not available. Try running "composer require symfony/twig-bundle".');
        $controller = new TemplateController();

        $controller->templateAction('mytemplate')->getContent();
        $controller('mytemplate')->getContent();
    }

    public function testContext()
    {
        $templateName = 'template_controller.html.twig';
        $context = [
            'param' => 'hello world',
        ];
        $expected = '<h1>'.$context['param'].'</h1>';

        $loader = new ArrayLoader();
        $loader->setTemplate($templateName, '<h1>{{param}}</h1>');

        $twig = new Environment($loader);
        $controller = new TemplateController($twig);

        $this->assertEquals($expected, $controller->templateAction($templateName, null, null, null, $context)->getContent());
        $this->assertEquals($expected, $controller($templateName, null, null, null, $context)->getContent());
    }

    public function testStatusCode()
    {
        $templateName = 'template_controller.html.twig';
        $statusCode = 201;

        $loader = new ArrayLoader();
        $loader->setTemplate($templateName, '<h1>{{param}}</h1>');

        $twig = new Environment($loader);
        $controller = new TemplateController($twig);

        $this->assertSame(201, $controller->templateAction($templateName, null, null, null, [], $statusCode)->getStatusCode());
        $this->assertSame(200, $controller->templateAction($templateName)->getStatusCode());
    }
}
