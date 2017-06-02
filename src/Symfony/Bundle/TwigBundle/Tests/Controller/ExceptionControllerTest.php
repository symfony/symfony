<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Controller;

use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\Controller\ExceptionController;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class ExceptionControllerTest extends TestCase
{
    public function testShowActionCanBeForcedToShowErrorPage()
    {
        $twig = new Environment(
            new ArrayLoader(array(
                '@Twig/Exception/error404.html.twig' => 'ok',
            ))
        );

        $request = Request::create('whatever', 'GET');
        $request->headers->set('X-Php-Ob-Level', 1);
        $request->attributes->set('showException', false);
        $exception = FlattenException::create(new \Exception(), 404);
        $controller = new ExceptionController($twig, /* "showException" defaults to --> */ true);

        $response = $controller->showAction($request, $exception, null);

        $this->assertEquals(200, $response->getStatusCode()); // successful request
        $this->assertEquals('ok', $response->getContent());  // content of the error404.html template
    }

    public function testFallbackToHtmlIfNoTemplateForRequestedFormat()
    {
        $twig = new Environment(
            new ArrayLoader(array(
                '@Twig/Exception/error.html.twig' => 'html',
            ))
        );

        $request = Request::create('whatever');
        $request->headers->set('X-Php-Ob-Level', 1);
        $request->setRequestFormat('txt');
        $exception = FlattenException::create(new \Exception());
        $controller = new ExceptionController($twig, false);

        $response = $controller->showAction($request, $exception);

        $this->assertEquals('html', $request->getRequestFormat());
    }
}
