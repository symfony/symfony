<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle\Controller;

use Symphony\Component\Debug\Exception\FlattenException;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\HttpFoundation\Request;

/**
 * PreviewErrorController can be used to test error pages.
 *
 * It will create a test exception and forward it to another controller.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class PreviewErrorController
{
    protected $kernel;
    protected $controller;

    public function __construct(HttpKernelInterface $kernel, $controller)
    {
        $this->kernel = $kernel;
        $this->controller = $controller;
    }

    public function previewErrorPageAction(Request $request, $code)
    {
        $exception = FlattenException::create(new \Exception('Something has intentionally gone wrong.'), $code);

        /*
         * This Request mimics the parameters set by
         * \Symphony\Component\HttpKernel\EventListener\ExceptionListener::duplicateRequest, with
         * the additional "showException" flag.
         */

        $subRequest = $request->duplicate(null, null, array(
            '_controller' => $this->controller,
            'exception' => $exception,
            'logger' => null,
            'format' => $request->getRequestFormat(),
            'showException' => false,
        ));

        return $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}
