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
        $twig = $this->createTwigEnv(array('@Twig/Exception/error404.html.twig' => '<html>not found</html>'));

        $request = $this->createRequest('html');
        $request->attributes->set('showException', false);
        $exception = FlattenException::create(new \Exception(), 404);
        $controller = new ExceptionController($twig, /* "showException" defaults to --> */ true);

        $response = $controller->showAction($request, $exception, null);

        $this->assertEquals(200, $response->getStatusCode()); // successful request
        $this->assertEquals('<html>not found</html>', $response->getContent());
    }

    public function testFallbackToHtmlIfNoTemplateForRequestedFormat()
    {
        $twig = $this->createTwigEnv(array('@Twig/Exception/error.html.twig' => '<html></html>'));

        $request = $this->createRequest('txt');
        $exception = FlattenException::create(new \Exception());
        $controller = new ExceptionController($twig, false);

        $controller->showAction($request, $exception);

        $this->assertEquals('html', $request->getRequestFormat());
    }

    public function testFallbackToHtmlWithFullExceptionIfNoTemplateForRequestedFormatAndExceptionsShouldBeShown()
    {
        $twig = $this->createTwigEnv(array('@Twig/Exception/exception_full.html.twig' => '<html></html>'));

        $request = $this->createRequest('txt');
        $request->attributes->set('showException', true);
        $exception = FlattenException::create(new \Exception());
        $controller = new ExceptionController($twig, false);

        $controller->showAction($request, $exception);

        $this->assertEquals('html', $request->getRequestFormat());
    }

    public function testResponseHasRequestedMimeType()
    {
        $twig = $this->createTwigEnv(array('@Twig/Exception/error.json.twig' => '{}'));

        $request = $this->createRequest('json');
        $exception = FlattenException::create(new \Exception());
        $controller = new ExceptionController($twig, false);

        $response = $controller->showAction($request, $exception);

        $this->assertEquals('json', $request->getRequestFormat());
        $this->assertEquals($request->getMimeType('json'), $response->headers->get('Content-Type'));
    }

    private function createRequest($requestFormat)
    {
        $request = Request::create('whatever');
        $request->headers->set('X-Php-Ob-Level', 1);
        $request->setRequestFormat($requestFormat);

        return $request;
    }

    private function createTwigEnv(array $templates)
    {
        return new Environment(new ArrayLoader($templates));
    }
}
