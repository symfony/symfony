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
        $twig = self::createMock(Environment::class);
        $twig->expects(self::exactly(2))->method('render')->willReturn('bar');

        $controller = new TemplateController($twig);

        self::assertEquals('bar', $controller->templateAction('mytemplate')->getContent());
        self::assertEquals('bar', $controller('mytemplate')->getContent());
    }

    public function testNoTwig()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('You cannot use the TemplateController if the Twig Bundle is not available.');
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

        self::assertEquals($expected, $controller->templateAction($templateName, null, null, null, $context)->getContent());
        self::assertEquals($expected, $controller($templateName, null, null, null, $context)->getContent());
    }

    public function testStatusCode()
    {
        $templateName = 'template_controller.html.twig';
        $statusCode = 201;

        $loader = new ArrayLoader();
        $loader->setTemplate($templateName, '<h1>{{param}}</h1>');

        $twig = new Environment($loader);
        $controller = new TemplateController($twig);

        self::assertSame(201, $controller->templateAction($templateName, null, null, null, [], $statusCode)->getStatusCode());
        self::assertSame(200, $controller->templateAction($templateName)->getStatusCode());
    }
}
