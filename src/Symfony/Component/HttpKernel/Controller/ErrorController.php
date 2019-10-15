<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Symfony\Component\ErrorRenderer\ErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\ErrorRendererNotFoundException;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Renders error or exception pages from a given FlattenException.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ErrorController
{
    private $kernel;
    private $controller;
    private $errorRenderer;

    public function __construct(HttpKernelInterface $kernel, $controller, ErrorRenderer $errorRenderer)
    {
        $this->kernel = $kernel;
        $this->controller = $controller;
        $this->errorRenderer = $errorRenderer;
    }

    public function __invoke(Request $request, FlattenException $exception): Response
    {
        try {
            return new Response($this->errorRenderer->render($exception, $request->getPreferredFormat()), $exception->getStatusCode(), $exception->getHeaders());
        } catch (ErrorRendererNotFoundException $_) {
            return new Response($this->errorRenderer->render($exception), $exception->getStatusCode(), $exception->getHeaders());
        }
    }

    public function preview(Request $request, int $code): Response
    {
        $exception = FlattenException::createFromThrowable(new \Exception('This is a sample exception.'), $code, ['X-Debug' => false]);

        /*
         * This Request mimics the parameters set by
         * \Symfony\Component\HttpKernel\EventListener\ExceptionListener::duplicateRequest, with
         * the additional "showException" flag.
         */
        $subRequest = $request->duplicate(null, null, [
            '_controller' => $this->controller,
            'exception' => $exception,
            'logger' => null,
            'showException' => false,
        ]);

        return $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}
